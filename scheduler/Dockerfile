FROM node:22-alpine
WORKDIR /app
COPY package.json ./
RUN npm install --omit=dev && rm -rf /root/.npm /tmp/*
COPY src ./src
RUN mkdir -p /app/data
VOLUME /app/data
EXPOSE 3000
CMD ["node", "src/index.js"]
