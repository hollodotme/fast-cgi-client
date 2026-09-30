// FastCGI application for the compatibility tests, served by the tokio-fastcgi crate.
// The contract it implements is described in ../../README.md.
use std::io::Read;
use std::sync::Arc;
use tokio::io::AsyncWrite;
use tokio::net::TcpListener;
use tokio_fastcgi::{OutStream, Request, RequestResult, Requests};

fn query_argument(query: &str, name: &str) -> usize {
    query
        .split('&')
        .filter_map(|pair| pair.split_once('='))
        .find(|(key, _)| *key == name)
        .and_then(|(_, value)| value.parse().ok())
        .unwrap_or(0)
}

async fn write_all<W: AsyncWrite + Unpin>(stream: &mut OutStream<W>, data: &[u8]) {
    let mut written = 0;

    while written < data.len() {
        match stream.write(&data[written..]).await {
            Ok(bytes) if bytes > 0 => written += bytes,
            _ => break,
        }
    }
}

async fn handle<W: AsyncWrite + Unpin>(request: Arc<Request<W>>) -> RequestResult {
    let param = |name: &str| request.get_str_param(name).unwrap_or("").to_string();
    let uri = param("REQUEST_URI");
    let path = uri.split('?').next().unwrap_or("").to_string();
    let query = param("QUERY_STRING");

    let mut response: Vec<u8> = Vec::new();

    match path.as_str() {
        "/echo" => {
            let mut body = Vec::new();
            request.get_stdin().read_to_end(&mut body).unwrap();

            let headers = format!(
                "Content-Type: text/plain\r\n\
                 X-Request-Method: {}\r\n\
                 X-Query-String: {}\r\n\
                 X-Content-Length: {}\r\n\
                 X-Custom-Param: {}\r\n\r\n",
                param("REQUEST_METHOD"),
                query,
                body.len(),
                param("COMPATIBILITY_TEST")
            );

            response.extend_from_slice(headers.as_bytes());
            response.extend_from_slice(&body);
        }
        "/output" => {
            response.extend_from_slice(b"Content-Type: text/plain\r\n\r\n");
            response.extend(std::iter::repeat(b'x').take(query_argument(&query, "bytes")));
        }
        "/status" => {
            let code = query_argument(&query, "code");
            let headers = format!("Status: {}\r\nContent-Type: text/plain\r\n\r\nStatus {}", code, code);

            response.extend_from_slice(headers.as_bytes());
        }
        "/capabilities" => {
            response.extend_from_slice(b"Content-Type: text/plain\r\n\r\nstderr");
        }
        "/stderr" => {
            write_all(&mut request.get_stderr(), b"Compatibility test error").await;
            response.extend_from_slice(b"Content-Type: text/plain\r\n\r\nstderr written");
        }
        _ => {
            response.extend_from_slice(b"Status: 404 Not Found\r\nContent-Type: text/plain\r\n\r\n");
        }
    }

    write_all(&mut request.get_stdout(), &response).await;

    RequestResult::Complete(0)
}

#[tokio::main]
async fn main() {
    let listener = TcpListener::bind("0.0.0.0:9000").await.unwrap();

    while let Ok((mut stream, _)) = listener.accept().await {
        tokio::spawn(async move {
            let mut requests = Requests::from_split_socket(stream.split(), 10, 10);

            while let Ok(Some(request)) = requests.next().await {
                if let Err(error) = request.process(handle).await {
                    eprintln!("Processing request failed: {}", error);
                }
            }
        });
    }
}
