// FastCGI application for the compatibility tests, served by sharpfastcgi (Grillisoft.FastCgi).
// The contract it implements is described in ../README.md.
using System;
using System.IO;
using System.Linq;
using System.Net;
using System.Net.Sockets;
using System.Text;
using System.Threading;
using Grillisoft.FastCgi;
using Grillisoft.FastCgi.Protocol;
using ByteArray = Grillisoft.ImmutableArray.ImmutableArray<byte>;

public class CompatibilityRequest : Request
{
    public CompatibilityRequest(ushort id, BeginRequestMessageBody body) : base(id, body)
    {
    }

    public override void Execute()
    {
        try
        {
            var uri = this.Parameters.GetValue("REQUEST_URI") ?? "";
            var path = uri.Split('?')[0];
            var query = this.Parameters.GetValue("QUERY_STRING") ?? "";

            switch (path)
            {
                case "/echo":
                    var body = new MemoryStream();
                    this.InputStream.CopyTo(body);

                    this.Write(
                        "Content-Type: text/plain\r\n"
                        + "X-Request-Method: " + this.Parameters.GetValue("REQUEST_METHOD") + "\r\n"
                        + "X-Query-String: " + query + "\r\n"
                        + "X-Content-Length: " + body.Length + "\r\n"
                        + "X-Custom-Param: " + this.Parameters.GetValue("COMPATIBILITY_TEST") + "\r\n\r\n"
                    );
                    this.OutputStream.Write(body.ToArray(), 0, (int)body.Length);
                    break;

                case "/output":
                    this.Write("Content-Type: text/plain\r\n\r\n" + new string('x', QueryArgument(query, "bytes")));
                    break;

                case "/status":
                    var code = QueryArgument(query, "code");
                    this.Write("Status: " + code + "\r\nContent-Type: text/plain\r\n\r\nStatus " + code);
                    break;

                case "/capabilities":
                    this.Write("Content-Type: text/plain\r\n\r\nstderr");
                    break;

                case "/stderr":
                    var error = Encoding.ASCII.GetBytes("Compatibility test error");
                    this.ErrorStream.Write(error, 0, error.Length);
                    this.Write("Content-Type: text/plain\r\n\r\nstderr written");
                    break;

                default:
                    this.Write("Status: 404 Not Found\r\nContent-Type: text/plain\r\n\r\n");
                    break;
            }
        }
        finally
        {
            this.End();
        }
    }

    public override void Abort()
    {
    }

    private void Write(string content)
    {
        var bytes = Encoding.ASCII.GetBytes(content);
        this.OutputStream.Write(bytes, 0, bytes.Length);
    }

    private static int QueryArgument(string query, string name)
    {
        var value = query.Split('&')
            .Select(pair => pair.Split('='))
            .Where(pair => pair.Length == 2 && pair[0] == name)
            .Select(pair => pair[1])
            .FirstOrDefault();

        return int.TryParse(value, out var number) ? number : 0;
    }
}

public class CompatibilityChannel : SimpleFastCgiChannel
{
    public CompatibilityChannel(ILowerLayer layer, ILoggerFactory loggerFactory) : base(layer, loggerFactory)
    {
    }

    protected override Request CreateRequest(ushort requestId, BeginRequestMessageBody body)
    {
        return new CompatibilityRequest(requestId, body);
    }
}

public class SilentLogger : ILogger
{
    public void Log(LogLevel level, string format, params object[] args)
    {
    }

    public void Log(LogLevel level, Exception ex, string format, params object[] args)
    {
    }
}

public class SilentLoggerFactory : ILoggerFactory
{
    public ILogger Create(Type type)
    {
        return new SilentLogger();
    }

    public ILogger Create(string name)
    {
        return new SilentLogger();
    }
}

// Transfers the bytes of one TCP connection to and from the FastCGI channel of sharpfastcgi.
// It replaces the TcpServer of sharpfastcgi, which does not notice connections closed by the client
// when the client asked to keep the connection open, and then blocks one thread per connection forever.
public class TcpConnection : ILowerLayer
{
    private readonly TcpClient client;
    private readonly NetworkStream stream;

    public TcpConnection(TcpClient client)
    {
        this.client = client;
        this.stream = client.GetStream();
    }

    public void Run()
    {
        var channel = new CompatibilityChannel(this, new SilentLoggerFactory());

        channel.RequestEnded += (sender, e) =>
        {
            if (!((Request)sender).RequestBody.KeepConnection)
            {
                this.client.Close();
            }
        };

        try
        {
            while (true)
            {
                var buffer = new byte[4096];
                var read = this.stream.Read(buffer, 0, buffer.Length);

                if (read <= 0)
                {
                    break;
                }

                channel.Receive(new ByteArray(buffer, read));
            }
        }
        catch (Exception)
        {
            // The connection was closed
        }
        finally
        {
            this.client.Close();
        }
    }

    public void Send(ByteArray data)
    {
        var bytes = new byte[data.Count];
        data.CopyTo(bytes, 0, data.Count);

        lock (this.stream)
        {
            this.stream.Write(bytes, 0, bytes.Length);
            this.stream.Flush();
        }
    }
}

public static class Program
{
    public static void Main()
    {
        var listener = new TcpListener(IPAddress.Any, 9000);
        listener.Start();

        while (true)
        {
            var connection = new TcpConnection(listener.AcceptTcpClient());

            new Thread(connection.Run) { IsBackground = true }.Start();
        }
    }
}
