# Zoho Circle Addon Sync Test Steps

1. Set env values:
   - `ZOHO_CIRCLE_ADDON_PRODUCT_ID` (Peers Global Membership - Avinash product id)
   - `ZOHO_BILLING_PRODUCT_ID` (fallback)
2. Run migrations:
   - `php artisan migrate`
3. Open admin circle edit page and set prices for:
   - Monthly (1)
   - Quarterly (3)
   - Half-Yearly (6)
   - Yearly (12)
4. Save the circle.
5. Verify Zoho Billing -> Product Catalog -> Addons has 4 addons:
   - `{Circle} - Monthly` (`interval_unit=monthly`)
   - `{Circle} - Quarterly` (`interval_unit=monthly`)
   - `{Circle} - Half-Yearly` (`interval_unit=monthly`)
   - `{Circle} - Yearly` (`interval_unit=yearly`)
6. Verify DB `circle_subscription_prices` rows for durations 1/3/6/12 have non-null:
   - `zoho_addon_id`
   - `zoho_addon_code`
   - `zoho_addon_name`
   - `zoho_addon_interval_unit`
   - `payload`
7. Send webhook with `addon.addon_code` and verify row payload syncs without SQL column errors.
