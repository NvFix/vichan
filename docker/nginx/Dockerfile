FROM nginx:1.31.9-alpine-slim

COPY . /code
RUN adduser --system www-data \
  && adduser www-data www-data

CMD [ "nginx", "-g", "daemon off;" ]
EXPOSE 80
