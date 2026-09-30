// FastCGI application for the compatibility tests, served by the fastcgi crate.
// The contract it implements is described in ../../README.md.
use std::io::{Read, Write};
use std::net::TcpListener;

fn query_argument(query: &str, name: &str) -> usize {
    query
        .split('&')
        .filter_map(|pair| pair.split_once('='))
        .find(|(key, _)| *key == name)
        .and_then(|(_, value)| value.parse().ok())
        .unwrap_or(0)
}

fn handle(mut request: fastcgi::Request) {
    let uri = request.param("REQUEST_URI").unwrap_or_default();
    let path = uri.split('?').next().unwrap_or("").to_string();
    let query = request.param("QUERY_STRING").unwrap_or_default();

    let mut response: Vec<u8> = Vec::new();

    match path.as_str() {
        "/echo" => {
            let mut body = Vec::new();
            request.stdin().read_to_end(&mut body).unwrap();

            let headers = format!(
                "Content-Type: text/plain\r\n\
                 X-Request-Method: {}\r\n\
                 X-Query-String: {}\r\n\
                 X-Content-Length: {}\r\n\
                 X-Custom-Param: {}\r\n\r\n",
                request.param("REQUEST_METHOD").unwrap_or_default(),
                query,
                body.len(),
                request.param("COMPATIBILITY_TEST").unwrap_or_default()
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
            request.stderr().write_all(b"Compatibility test error").unwrap();
            response.extend_from_slice(b"Content-Type: text/plain\r\n\r\nstderr written");
        }
        _ => {
            response.extend_from_slice(b"Status: 404 Not Found\r\nContent-Type: text/plain\r\n\r\n");
        }
    }

    request.stdout().write_all(&response).unwrap();
}

fn main() {
    let listener = TcpListener::bind("0.0.0.0:9000").unwrap();

    fastcgi::run_tcp(handle, &listener);
}
