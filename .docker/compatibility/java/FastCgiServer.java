// FastCGI application for the compatibility tests, using the server classes of jFastCGI.
// The contract it implements is described in ../README.md.

import com.fastcgi.FCGIConstants;
import com.fastcgi.FCGIInputStream;
import com.fastcgi.FCGIMessage;
import com.fastcgi.FCGIOutputStream;
import com.fastcgi.FCGIRequest;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.OutputStream;
import java.net.ServerSocket;
import java.net.Socket;
import java.nio.charset.StandardCharsets;
import java.util.Properties;

public class FastCgiServer {

    public static void main(String[] args) throws IOException {
        try (ServerSocket server = new ServerSocket(9000)) {
            while (true) {
                Socket socket = server.accept();

                new Thread(() -> serve(socket)).start();
            }
        }
    }

    /**
     * Serves all requests of one connection. It does what FCGIInterface of jFastCGI does, but for every
     * connection in its own thread. FCGIInterface handles one connection at a time and waits for the next
     * request of a connection that the client keeps open, so requests of other connections are not served.
     */
    private static void serve(Socket socket) {
        try (socket) {
            while (true) {
                FCGIRequest request = new FCGIRequest();
                request.setSocket(socket);
                request.setBeginProcessed(false);
                request.setInputStream(new FCGIInputStream(socket.getInputStream(), 8192, 0, request));
                request.getInputStream().fill();

                if (!request.isBeginProcessed()) {
                    return;
                }

                request.setParameters(new Properties());
                request.getInputStream().setReaderType(FCGIConstants.TYPE_PARAMS);

                if (!new FCGIMessage(request.getInputStream()).readParams(request.getParameters())) {
                    return;
                }

                request.getInputStream().setReaderType(FCGIConstants.TYPE_STDIN);
                request.setOutputStream(
                        new FCGIOutputStream(socket.getOutputStream(), 8192, FCGIConstants.TYPE_STDOUT, request));
                request.setErrorStream(
                        new FCGIOutputStream(socket.getOutputStream(), 512, FCGIConstants.TYPE_STDERR, request));
                request.setNumWriters(2);

                handle(request);

                request.getErrorStream().close();
                request.getOutputStream().close();

                if (!request.isKeepConnection()) {
                    return;
                }
            }
        } catch (IOException e) {
            // The connection was closed
        }
    }

    private static void handle(FCGIRequest request) throws IOException {
        Properties params = request.getParameters();
        String path = params.getProperty("REQUEST_URI", "").split("\\?")[0];
        String query = params.getProperty("QUERY_STRING", "");
        OutputStream output = request.getOutputStream();

        switch (path) {
            case "/echo":
                ByteArrayOutputStream body = readBody(request);

                write(output, "Content-Type: text/plain\r\n"
                        + "X-Request-Method: " + params.getProperty("REQUEST_METHOD", "") + "\r\n"
                        + "X-Query-String: " + query + "\r\n"
                        + "X-Content-Length: " + body.size() + "\r\n"
                        + "X-Custom-Param: " + params.getProperty("COMPATIBILITY_TEST", "") + "\r\n\r\n");
                output.write(body.toByteArray());
                break;

            case "/output":
                write(output, "Content-Type: text/plain\r\n\r\n" + "x".repeat(queryArgument(query, "bytes")));
                break;

            case "/status":
                int code = queryArgument(query, "code");
                write(output, "Status: " + code + "\r\nContent-Type: text/plain\r\n\r\nStatus " + code);
                break;

            case "/capabilities":
                write(output, "Content-Type: text/plain\r\n\r\nstderr");
                break;

            case "/stderr":
                write(request.getErrorStream(), "Compatibility test error");
                write(output, "Content-Type: text/plain\r\n\r\nstderr written");
                break;

            default:
                write(output, "Status: 404 Not Found\r\nContent-Type: text/plain\r\n\r\n");
        }
    }

    /**
     * FCGIInputStream returns 0 instead of -1 at the end of the request body, if multiple bytes are read at once.
     * So the stream methods of Java, which read until -1, would never end.
     */
    private static ByteArrayOutputStream readBody(FCGIRequest request) throws IOException {
        ByteArrayOutputStream body = new ByteArrayOutputStream();
        byte[] buffer = new byte[8192];
        int read;

        while ((read = request.getInputStream().read(buffer, 0, buffer.length)) > 0) {
            body.write(buffer, 0, read);
        }

        return body;
    }

    private static void write(OutputStream stream, String content) throws IOException {
        stream.write(content.getBytes(StandardCharsets.ISO_8859_1));
    }

    private static int queryArgument(String query, String name) {
        for (String pair : query.split("&")) {
            String[] parts = pair.split("=", 2);

            if (parts.length == 2 && parts[0].equals(name)) {
                try {
                    return Integer.parseInt(parts[1]);
                } catch (NumberFormatException e) {
                    return 0;
                }
            }
        }

        return 0;
    }
}
