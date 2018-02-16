# escape=` (backtick)

# get the official nodejs image (official ~= smaller == better ) --> Use an official Node runtime as a parent image
FROM node:8.9.2

#set container label information
# should mention the open source nature, etc...

# Copy the current directory contents into the container at /app
COPY . /app

# goto the app and get the required node packages
WORKDIR /app/server-nodejs
RUN npm install

# Make ports available to the world outside this container
EXPOSE 8090

#check every few minutes that we're still serving our page within three seconds
HEALTHCHECK --interval=5m --timeout=3s `
  CMD curl -f http://localhost:8090 || exit 1
  
# Run the server when the container launches; args can be overridden by docker run
ENTRYPOINT ["npm"]
CMD ["run","start"]
