FROM node:20-slim

WORKDIR /app

# Copy package files
COPY package*.json ./

# Copy api-client source
COPY api-client ./api-client

# Build api-client first (compile TypeScript to JavaScript)
WORKDIR /app/api-client
RUN npm install && npm run build

# Return to app directory and install all dependencies
WORKDIR /app
RUN npm install

# Copy rest of application (excluding node_modules and api-client via .dockerignore)
# We need to be selective here to not overwrite the built api-client
COPY src ./src
COPY angular.json ./
COPY tsconfig.* ./
COPY karma.conf.js ./
COPY firebase.json ./
COPY swa-cli.config.json ./
COPY docker-entrypoint.sh ./
COPY docker ./docker

# Set executable permission for entrypoint
RUN chmod +x /app/docker-entrypoint.sh

EXPOSE 4200

ENTRYPOINT ["/app/docker-entrypoint.sh"]
CMD ["npm", "start"]
