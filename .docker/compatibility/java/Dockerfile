# FastCGI server: the server module of jFastCGI, https://github.com/jFastCGI/jfastcgi
FROM eclipse-temurin:25-jdk AS build

RUN apt-get update && apt-get install -y --no-install-recommends git && rm -rf /var/lib/apt/lists/*

# The server module is not published as an artifact of its own, so it is compiled from its sources
RUN git clone --depth 1 https://github.com/jFastCGI/jfastcgi.git /jfastcgi

WORKDIR /app
COPY FastCgiServer.java ./
RUN javac -d /out $(find /jfastcgi/server/src/main/java -name '*.java') FastCgiServer.java

FROM eclipse-temurin:25-jre

COPY --from=build /out /app

EXPOSE 9000
CMD ["java", "-cp", "/app", "FastCgiServer"]
