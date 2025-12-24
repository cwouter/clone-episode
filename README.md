 # README
 
 ## Prerequisites
 - **Docker + Docker Compose**
 - **(Optional)** Node.js/npm if you want to build frontend assets locally
 
 ## Start the project (quick steps)
 1. **Prepare environment file**
    - For demo purposes, the .env is already included in git. 
 
 2. **Build and start the containers**
    ```bash
    docker compose up -d --build
    ```
 
 3. **Install dependencies (inside the container)**
    ```bash
    docker compose exec app composer install
    ```
 
 4. **Run database migrations**
    ```bash
    docker compose exec app php artisan migrate:refresh
    ```
    
5. **Seed the database with fake data**
    ```bash
    docker compose exec app php artisan db:seed
    ```
 
 
 ## What is running?
 - **Laravel app**: runs in the `app` container.
 - **Nginx**: `http://localhost:8080`
 - **Postgres**: `localhost:5432`
 - **Redis**: `localhost:6379`
 - **RabbitMQ (management UI)**: `http://localhost:15672` (default `guest`/`guest`)
 - **LocalStack (S3)**: `http://localhost:4566`
 - **Queue worker**: runs as a separate container `queue-worker`.
 
 ## Useful commands
 - **View logs**
   ```bash
   docker compose logs -f app
   docker compose logs -f queue-worker
   ```
 
 - **Run tests**
   ```bash
   docker compose exec app php artisan test
   ```
 
 ## Stop
 ```bash
 docker compose down
 ```
