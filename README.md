
# What am i

GpsImage here! I'm not the löatest class ... but still gold.
Your class to get some inforamtion out of images.
You can use it to get the position on Google Maps or even the raw coords.
Yopu could also combine it with an FB API called graph. Could be fun ;)


# Use me

```php
$gps1 = new GpsImage(['image1.jpg', 'image2.jpg']);
$gps2 = new GpsImage('image1.jpg');

$link = $gps1->init(true);
$someImageInfo = $gps2->init();
```

