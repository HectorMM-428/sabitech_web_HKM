# SABITECH WEB · frontend (contenedor `front`)
# Etapas:
#   build  compila la SPA de React con Node 26 LTS
#   dev    servidor de Vite con recarga en caliente (compose.override.yaml)
#   prod   Nginx: sirve la SPA, termina HTTPS y pasa /api y /sanctum al contenedor back

FROM node:26-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
ARG VITE_API_BASE_URL=/api/v1
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}
RUN npm run build

# ---------------------------------------------------------------------------
FROM node:26-alpine AS dev
WORKDIR /app
EXPOSE 5173
CMD ["sh", "-c", "npm ci --no-audit --no-fund && npm run dev -- --host 0.0.0.0"]

# ---------------------------------------------------------------------------
FROM nginx:stable-alpine AS prod
# La imagen oficial sustituye ${SERVER_NAME} en las plantillas al arrancar.
COPY nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80 443
