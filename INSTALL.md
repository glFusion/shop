# Installation and removal of the Shop plugin for glFusion

## Important
1. Back-up your database.
    * visit `http://{site_url}/admin/database.php`

## Automatic Installation
This plugin supports the automatic installation method for glFusion 1.1.2
and higher.  Simply upload the plugin via the plugin administration
interface and install it.

If this will not work for you (due to filesystem permissions, for example),
then you may manually install the plugin.

## Manual Installation
1. Uncompress the plugin tar file into <private_dir>/plugins
    ```
    cd <private_dir>/plugins`
    tar xzvf </path/to/shop_xxx_xxx.tar.gz>
    ```

2. Move the `admin` and `public_html` directories to locations in the glFusion
   HTML directory. `<public_html>/admin/plugins/shop` and 
   `<public_html>/shop` respecitivly.
    ```
    mv <private_dir>/plugins/shop/admin <public_html>/admin/plugins/shop`
    mv <private_dir>/plugins/shop/public_html <public_html>/shop`
    ```

3. Crete the working directories for the plugin.
```
    touch <private>/logs/shop_downloads.log
    chmod 666 <private>/logs/shop_downloads.log
    cd private/data
    mkdir shop
    mkdir shop/keys
    mkdir shop/cache
    mkdir shop/files
    mkdir shop/images
    mkdir shop/images/brands
    mkdir shop/images/products
    mkdir shop/images/categories
```

4. As one of your site's Root users, run the shop installation program.
    - visit `http://{site_url}/admin/plugins/shop/install.php`
    - click on the `install` button

5. If the installation completed successfully you can skip to the next step.
   Otherwise, check the glFusion error.log for errors.

7. Configure the plugin.  Be sure to set your primary Shop business email
   address as Receiver Address #0.

## Removal
1. Back-up your site database, just in case.
    - visit `http://{site_url}/admin/database.php`
 
2. Visit the plugin administration area of your site and delete the Shop plugin.
