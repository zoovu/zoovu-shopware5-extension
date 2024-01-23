## Site Search 360 Shopware 5 Extension
***

INSTALLATION SHOPWARE 5 EXTENSION:

For each sales channel (language or vertical/store view), all settings must first be entered in the Shopware plugin configuration. This includes especially ProjectID, ApiKey and language.

Installation:
You may include the repository via Compose into your project. The extension should then be found in the /custom/plugins folder.

The installation can be finalized like other Shopware 5 plugins as well, via the Plugin-Manager in the Shopware 5 Admin-UI. You will find a new menu entry "Site Search 360" which will allow you to insert the credentials "projectId/customerId" and "ApiKey" to start the upload of the product data (data export, full update).


Usually the full update should run once within 24h to keep the data up to date. Therefor, the last step would be to activate the Shopware cronjobs for the extension to make sure, the full updates as well as the incremental updates get triggered. In Shopware 5 this can be found in the Admin-UI within "Settings/Base settings/Cronjobs". You may set up different cron setting for incremental and full updates. Important: please make sure that Shopware cronjobs get triggered by the system cronjob, see the official Shopware 5 documentation here: https://docs.shopware.com/en/shopware-5-en/settings/system-cronjobs

You may test if the upload procedure runs fine by running within the Shopware installation folder: 
    sudo -u www-data php bin/console sw:cron:run SemknoxUpdateDataJob -f

Finally you will find an extra Site Search 360 entry in the main menu of the Shopware 5 Admin-UI. There you may see current logging information for a running update cycle as well as a button to trigger an data export manually.

***
Questions:

Q: When gets a product uploaded to the API?

A: The upload process for products takes place in 2 stages:

1. Collect the currently available products
2. JSON creation of the individual product data

Stage 1: the criteria for a product to get uploaded in this stage are:

* all products that belong to the respective sales channel
* it needs to be active
* and those, that are not on sale (Abverkauf), or - if they are on sale - still have the required minimum sales quantity in stock.

These filters are applied directly by the plugin via a SQL query

Stage 2: After the actual JSON data was created per product, at the end it is evaluated whether an URL is available for the product and whether it is valid. If not, the product will not be uploaded.
