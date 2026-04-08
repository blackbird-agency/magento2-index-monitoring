<?php
declare(strict_types=1);

namespace Blackbird\IndexMonitoring\Model;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    public const XML_PATH_ENABLED = 'blackbird_index_monitoring/general/enabled';
    public const XML_PATH_RECIPIENTS = 'blackbird_index_monitoring/general/recipients';
    public const XML_PATH_THRESHOLD_MINUTES = 'blackbird_index_monitoring/general/threshold_minutes';
    public const XML_PATH_IDLE_PENDING_THRESHOLD_MINUTES = 'blackbird_index_monitoring/general/idle_pending_threshold_minutes';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) (int) $this->scopeConfig->getValue(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getRecipientsRaw(): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_RECIPIENTS, ScopeInterface::SCOPE_STORE) ?? '');
    }

    public function getThresholdMinutes(): int
    {
        return (int) ($this->scopeConfig->getValue(self::XML_PATH_THRESHOLD_MINUTES, ScopeInterface::SCOPE_STORE));
    }

    public function getIdlePendingThresholdMinutes(): int
    {
        return (int) ($this->scopeConfig->getValue(self::XML_PATH_IDLE_PENDING_THRESHOLD_MINUTES, ScopeInterface::SCOPE_STORE));
    }
}
