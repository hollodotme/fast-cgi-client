# FastCGI server: net/http/fcgi of the Go standard library, https://pkg.go.dev/net/http/fcgi
FROM golang:alpine AS build

WORKDIR /app
COPY go.mod main.go ./
RUN go build -o /fastcgi-server .

FROM alpine

COPY --from=build /fastcgi-server /fastcgi-server

EXPOSE 9000
CMD ["/fastcgi-server"]
