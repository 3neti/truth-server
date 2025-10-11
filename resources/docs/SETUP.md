```bash
npm run dev
php artisan install:broadcasting --reverb
php artisan vendor:publish --tag=truth-election-ui-stubs --force
php artisan vendor:publish --tag=truth-qr-ui-stubs --force
php artisan reverb:start -v
```

update HandleInertiaRequests.php

```php
use TruthElection\Support\ElectionStoreInterface;

public function share(Request $request): array
{
    //...
    return [
        //add this part
        'precinct' => app(ElectionStoreInterface::class)->getPrecinct(),
    ];
}
```
