# Use an official PHP image with Apache
FROM php:8.1-apache

# Install PHP extensions required by mysqli and potentially others
# List all extensions your app needs. mysqli is essential for database connections.
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli pdo_mysql

# Enable Apache's mod_rewrite (needed for clean URLs if you use .htaccess)
RUN a2enmod rewrite

# Copy your application code into the Apache web root
# Your project root (Movieticketbooking) goes into /var/www/html/
COPY . /var/www/html/

# Configure Apache to serve index.php first and to respect .htaccess
# This might already be default, but ensures consistency
# Commented out as .htaccess file doesn't exist in the repository
# COPY ./.htaccess /var/www/html/.htaccess # Copy your existing .htaccess if you have one
COPY ./apache-config.conf /etc/apache2/sites-available/000-default.conf # See below for apache-config.conf

# Expose port 80 (Apache's default port)
EXPOSE 80

# Command to run Apache web server
CMD ["apache2-foreground"]
