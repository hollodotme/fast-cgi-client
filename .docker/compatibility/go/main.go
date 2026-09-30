// FastCGI application for the compatibility tests, served by Go's net/http/fcgi package.
// The contract it implements is described in ../README.md.
package main

import (
	"fmt"
	"io"
	"log"
	"net"
	"net/http"
	"net/http/fcgi"
	"strconv"
	"strings"
)

func handle(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/plain")

	switch r.URL.Path {
	case "/echo":
		body, err := io.ReadAll(r.Body)
		if err != nil {
			http.Error(w, err.Error(), http.StatusInternalServerError)
			return
		}

		w.Header().Set("X-Request-Method", r.Method)
		w.Header().Set("X-Query-String", r.URL.RawQuery)
		w.Header().Set("X-Content-Length", strconv.Itoa(len(body)))
		w.Header().Set("X-Custom-Param", fcgi.ProcessEnv(r)["COMPATIBILITY_TEST"])
		w.Write(body)

	case "/output":
		bytes, _ := strconv.Atoi(r.URL.Query().Get("bytes"))
		io.WriteString(w, strings.Repeat("x", bytes))

	case "/status":
		code, _ := strconv.Atoi(r.URL.Query().Get("code"))
		w.WriteHeader(code)
		fmt.Fprintf(w, "Status %d", code)

	case "/capabilities":
		// net/http/fcgi offers no way to write to the FastCGI error stream

	default:
		w.WriteHeader(http.StatusNotFound)
	}
}

func main() {
	listener, err := net.Listen("tcp", ":9000")
	if err != nil {
		log.Fatal(err)
	}

	log.Fatal(fcgi.Serve(listener, http.HandlerFunc(handle)))
}
