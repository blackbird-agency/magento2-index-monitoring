### Blackbird Index Monitoring (Magento 2.4.6 / PHP 8.3)

Magento index and mview state monitoring module.

Features:
- Email alert if an indexer remains in "working" status beyond a threshold (configurable, 60 minutes by default).
- Email alert if a mview_state is in "suspended" or (if used by a third-party module) "error" status.
- Email alert if a mview_state remains in "working" status beyond the same threshold.
- Cron job every 5 minutes (can be enabled/disabled via configuration).
- Anti-spam: only one email per incident sequence (resent only if the list of incidents changes or becomes empty then errors again).
- Dedicated log file: var/log/blackbird_index_monitoring.log (ERROR level).
- Admin action to bulk reset mview states (System > Index Management > Mview States).
- Console command to check indexer status on demand.

Installation:

### Via Composer
```bash
composer require blackbird/magento2-index-monitoring
bin/magento module:enable Blackbird_IndexMonitoring
bin/magento setup:upgrade
bin/magento cache:flush
```


Configuration (Admin):
- Stores > Configuration > Advanced > Blackbird - Index Monitoring
  - Enable monitoring (Yes/No)
  - Stuck threshold (minutes)
  - Email recipients (comma-separated email addresses)

Admin Actions:
- System > Index Management > Mview States
  - View all mview states with their status and updated time
  - Mass action to reset mview states to "idle" status

Console Commands:
- bin/magento blackbird:indexer:check-status
  - Manually check indexer and mview status
  - Returns exit code 1 if issues are detected (useful for CI/CD pipelines)

Technical Details:
- Cron: etc/crontab.xml (*/5 * * * *), class: Blackbird\\IndexMonitoring\\Model\\Cron\\MonitorJob
- Detection:
  - Indexers: Magento\\Indexer\\Model\\Indexer\\Collection, "working" status + updated field (epoch) > threshold
  - Mview: Magento\\Framework\\Mview\\View\\State\\CollectionInterface, "suspended" or "error" statuses, or "working" too long
- Notification: email template etc/email_templates.xml, file view/frontend/email/index_alert.html, sender: "General" identity
- Deduplication: persistent digest in database via Custom Variable (variable/variable_value tables),
  code: blackbird_index_monitoring_last_digest (Global scope / store_id=0)
- Dedicated log: var/log/blackbird_index_monitoring.log

Notes:
- The "error" mview status is not standard in Magento (idle/working/suspended). The module interprets it if set by a third party.
- Email is sent only if at least one valid recipient is configured.

Troubleshooting:
- Check Magento cron (system.cron) and server cron jobs.
- Review var/log/blackbird_index_monitoring.log for alert records.
- Useful commands:
  - warden env exec php-fpm bin/magento indexer:status
  - warden env exec php-fpm bin/magento indexer:show-mode
  - warden env exec php-fpm bin/magento indexer:reset <indexer_id>
  - warden env exec php-fpm bin/magento indexer:reindex <indexer_id>
  - warden env exec php-fpm bin/magento blackbird:indexer:check-status
