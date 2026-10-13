---
title: "Installation"
description: "Install the package with Composer and optionally publish the config file."
sidebar:
  order: 2
---

```bash
composer require kaihempel/erecht24-laravel
php artisan vendor:publish --tag=erecht24-config
```

The service provider and the `ERecht24` facade alias are auto-discovered. Publishing the config file is optional; every setting can be controlled from `.env`.
