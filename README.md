Analysis of popular composer packages
-------------------------------------

This repository contains a couple of scripts to download the most popular composer packages and
analyze them. Usage:

```
# Download 1000 most popular packages
php download.php 0 1000
./extract.sh
```

The `zipballs/` directory contains downloaded archives, while `sources/` contains the extracted
sources.
