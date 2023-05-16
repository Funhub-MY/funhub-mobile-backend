# FUNHUB PLATFORM

This project serves as the API and Admin Panel for Funhub mobile app and its platform.

## Developers

**NEDEX GROUP SDN BHD [https://nedex.io]**

Elson Tan (Project Lead)

Steven Yew (Web Developer)

Daniel Wong (Web Developer)


## Requirements
PHP ^8.1

MySQL 5.7

## Setup

1. `composer install`
2. `cp .env.example .env`
3. `php artisan migrate --seed`
4. `php artisan optimize`

## Development Pratice for Resource
1. Create Migrations.
2. Create Model.
3. Create Filamenet Resource.
4. Create Laravel Resource.
5. Create Controller Logic which implements Laravel Resource when return as JSON.
6. Ensure controller methods are documented for API using Sribe.
7. Create Unit Test for the Controller.
8. Register appropiate routes.


## Useful Commands

### Generate API DOCS
`php artisan scribe:generate`

### Generate Filament Resource (For Admin)
`php artisan filamenet:resource`

### Generate Resource Controllers
`php artisan make:controller MyController --resource`

