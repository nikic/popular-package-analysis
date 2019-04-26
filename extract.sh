#!/bin/bash
GLOBIGNORE=".:.."
for zip in zipballs/*/*.zip; do
    target=${zip/zipballs/sources}
    target=${target/.zip/}
    echo $target
    if [ -d $target ]; then
        continue
    fi

    echo "Extracting..."
    mkdir -p $target
    unzip $zip -d $target
    subdir=($target/*)
    mv $subdir/* $target
    rmdir $subdir
done
