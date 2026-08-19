# WordPress.org page assets

These files are **not** part of the plugin package. They belong in the
`assets/` directory at the root of the SVN repository, next to `trunk/`
and `tags/`, and are used only to render https://wordpress.org/plugins/colisly.

| File | Purpose |
| --- | --- |
| `icon.svg` | Vector icon, preferred by the directory when present |
| `icon-128x128.png` | Icon fallback (standard density) |
| `icon-256x256.png` | Icon fallback (high density) |
| `banner-772x250.png` | Page header banner |
| `banner-1544x500.png` | Page header banner (high density) |
| `screenshot-N.png` | Matches the Nth caption under `== Screenshots ==` in readme.txt |

## Publishing

```
cd <svn checkout>
svn add assets --force
svn commit -m "Update plugin assets" --username pixfeed
```

Assets take effect as soon as they are committed; unlike the plugin code,
they are not tied to a version tag.
