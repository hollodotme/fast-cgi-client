# FastCGI server: sharpfastcgi (Grillisoft.FastCgi), https://github.com/gigi81/sharpfastcgi
FROM mcr.microsoft.com/dotnet/sdk:10.0 AS build

WORKDIR /app
COPY FastCgiServer.csproj Program.cs ./
RUN dotnet publish -c Release -o /out

FROM mcr.microsoft.com/dotnet/runtime:10.0

COPY --from=build /out /app

EXPOSE 9000
CMD ["dotnet", "/app/fastcgi-server.dll"]
