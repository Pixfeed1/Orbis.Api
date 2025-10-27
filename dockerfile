FROM mcr.microsoft.com/dotnet/aspnet:8.0 AS base
WORKDIR /app
EXPOSE 5006

FROM mcr.microsoft.com/dotnet/sdk:8.0 AS build
WORKDIR /src
COPY ["Orbis.Web.Api/Orbis.Web.Api.csproj", "./Orbis.Web.Api/"]
COPY ["Orbis.Core.Domain/Orbis.Core.Domain.csproj", "./Orbis.Core.Domain/"]
COPY ["Orbis.Core.Business/Orbis.Core.Business.csproj", "./Orbis.Core.Business/"]
RUN dotnet restore "./Orbis.Web.Api/Orbis.Web.Api.csproj"
COPY . .
WORKDIR "/src/."
RUN dotnet build "./Orbis.Web.Api/Orbis.Web.Api.csproj" -c Release -o /app/build

FROM build AS publish
RUN dotnet publish "./Orbis.Web.Api/Orbis.Web.Api.csproj" -c Release -o /app/publish /p:UseAppHost=false

FROM base AS final
WORKDIR /app
COPY --from=publish /app/publish .
ENTRYPOINT ["dotnet", "Orbis.Web.Api.dll"]