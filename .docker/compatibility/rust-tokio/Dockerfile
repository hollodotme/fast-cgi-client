# FastCGI server: the tokio-fastcgi crate, https://github.com/FlashSystems/tokio-fastcgi
FROM rust:alpine AS build

WORKDIR /app
COPY Cargo.toml ./
COPY src ./src
RUN cargo build --release

FROM alpine

COPY --from=build /app/target/release/fastcgi-server /fastcgi-server

EXPOSE 9000
CMD ["/fastcgi-server"]
