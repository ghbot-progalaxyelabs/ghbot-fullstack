FROM node:20-slim

WORKDIR /app

COPY package*.json ./

COPY api-client ./api-client

RUN npm install

COPY . .

# Copy and set executable permission for entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 4200

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["npm", "start"]
