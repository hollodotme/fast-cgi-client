# Compatibility with FastCGI servers of other programming languages

Each directory in here contains a small FastCGI application that is served by the FastCGI server implementation
of another programming language. The compatibility tests in `tests/Compatibility` send requests to such an
application with the client of this library and check the responses.

Run the tests against one server with `make test-compatibility-<directory>`, e.g. `make test-compatibility-go`,
or against all servers with `make test-compatibility`.

## Adding a server

1. Create a directory `.docker/compatibility/<name>` with a `Dockerfile` that builds and starts the application.
2. The application must listen for FastCGI connections on TCP port `9000` and implement the contract below.

3. Add a workflow `.github/workflows/compatibility-<name>.yml` like the existing ones. It calls the reusable workflow
   `.github/workflows/compatibility.yml` with the name of the directory, and its `name` is the text of its badge.
4. Add the badge of the workflow to the `README.md` of the project.

The Makefile picks up every directory in here, `make test-compatibility` runs all of them.

The compatibility workflows run independently of the CI & Release workflow of the library: a failing compatibility
check does not block a release.

## Contract of the application

The application is a FastCGI responder. It decides what to do by the path of the `REQUEST_URI` parameter and
reads arguments from the `QUERY_STRING` parameter. Every response has a `Content-Type: text/plain` header.

| Path            | Response                                                                                         |
|-----------------|--------------------------------------------------------------------------------------------------|
| `/echo`         | Body: the request body, byte for byte. Headers: `X-Request-Method` (value of `REQUEST_METHOD`), `X-Query-String` (value of `QUERY_STRING`), `X-Content-Length` (number of bytes read from the request body) and `X-Custom-Param` (value of the parameter `COMPATIBILITY_TEST`). |
| `/output`       | Body: the character `x`, repeated as often as the query argument `bytes` says.                    |
| `/status`       | Header: `Status` with the status code given in the query argument `code`. Body: `Status <code>`.  |
| `/capabilities` | Body: one optional feature per line that the application supports, see below.                     |
| anything else   | Header: `Status: 404 Not Found`.                                                                  |

Optional features, because not every server implementation offers them:

| Feature  | Path      | Response                                                                                       |
|----------|-----------|------------------------------------------------------------------------------------------------|
| `stderr` | `/stderr` | Writes `Compatibility test error` to the FastCGI error stream. Body: `stderr written`.          |
