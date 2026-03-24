FROM node:22-bookworm-slim

RUN apt-get update && apt-get install -y --no-install-recommends git \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY package*.json ./
RUN npm ci --omit=dev

COPY dist/ ./dist/

ENV DATA_DIR=/data
EXPOSE 3000

CMD ["node", "dist/index-remote.js"]
