# Build stage
FROM node:22-alpine AS build

WORKDIR /app

RUN corepack enable && corepack prepare pnpm@10.15.1 --activate

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile --dangerously-allow-all-builds

COPY . .

ARG NUXT_PUBLIC_API_BASE=/api
ENV NUXT_PUBLIC_API_BASE=$NUXT_PUBLIC_API_BASE

RUN pnpm generate

# Runtime: Apache (local)
FROM httpd:2.4-alpine

COPY docker/httpd.conf /usr/local/apache2/conf/httpd.conf
COPY --from=build /app/.output/public/ /usr/local/apache2/htdocs/

EXPOSE 80

CMD ["httpd-foreground"]
