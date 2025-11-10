FROM node:20-slim

WORKDIR /app

COPY package*.json ./

COPY api-client ./api-client

RUN npm install

COPY . .

EXPOSE 4200

CMD ["npm", "start"]
