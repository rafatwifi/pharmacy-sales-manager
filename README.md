# Pharmacy Sales Manager

Medical/pharmacy sales tracking app for managing products, recording sales by pharmacy, and viewing reports.

## Features

- Product management with images and sales targets
- Direct sales recording with pharmacy name and quantity
- Sales reports by product and pharmacy
- Arabic RTL interface with Cairo font

## Requirements

- PHP 7.4+ with file upload support

## Run locally

```bash
cd dr-omar
php -S localhost:8000
```

Open:
- http://localhost:8000/dashboard.php
- http://localhost:8000/products.php
- http://localhost:8000/reports.php

## Data

Sales and products are stored in JSON files:
- `data.json` — products
- `sales.json` — sales transactions

Product images are saved under `uploads/products/`.
