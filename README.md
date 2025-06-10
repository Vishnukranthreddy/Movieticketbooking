# Movie Ticket Booking System

A web application for booking movie tickets online.

## Deployment Instructions

### Prerequisites
- Docker and Docker Compose installed
- MySQL database (default port: 3306)

### Configuration
1. Create a `.env` file in the root directory with the following content:
```
DB_HOST=your_database_host
DB_PORT=3306
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
DB_DATABASE=your_database_name
```

2. Make sure the `apache-config.conf` file exists in the root directory.

### Deployment with Docker
1. Build and start the containers:
```
docker-compose up -d
```

2. Access the application at http://localhost

### Deployment on Render.com
1. Connect your GitHub repository to Render.com
2. Create a new Web Service
3. Select "Docker" as the environment
4. Set the following environment variables:
   - DB_HOST: your_database_host
   - DB_PORT: 3306
   - DB_USERNAME: your_database_username
   - DB_PASSWORD: your_database_password
   - DB_DATABASE: your_database_name
5. Deploy the application

## Database
The application uses MySQL on port 3306. Make sure this port is open and accessible from your deployment environment.

## License
[Your License Information]