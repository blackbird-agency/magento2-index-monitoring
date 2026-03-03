<?php
declare(strict_types=1);

namespace Blackbird\IndexMonitoring\Model\Notifier;

use Blackbird\IndexMonitoring\Model\Config;
use Magento\Framework\App\State;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface as InlineTranslation;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Area;

class EmailNotifier
{
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly InlineTranslation $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly State $state
    ) {
    }

    /**
     * @param array{indexers: array<int, array<string, mixed>>, mviews: array<int, array<string, mixed>>} $issues
     */
    public function notify(array $issues): void
    {
        $this->state->setAreaCode(Area::AREA_ADMINHTML);
        $recipients = $this->parseRecipients($this->config->getRecipientsRaw());

        if (empty($recipients)) {
            return;
        }

        $content = $this->buildContent($issues);

        $this->inlineTranslation->suspend();
        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('blackbird_index_monitoring_alert')
                ->setTemplateOptions([
                    'area'  => Area::AREA_FRONTEND,
                    'store' => (int) $this->storeManager->getStore()->getId(),
                ])
                ->setTemplateVars([
                    'content' => $content,
                ])
                ->setFromByScope('general')
                ->addTo($recipients)
                ->getTransport();

            $transport->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    private function parseRecipients(string $raw): array
    {
        $emails = array_filter(array_map('trim', explode(',', $raw)));
        $emails = array_values(array_filter($emails, static fn(string $email): bool => (bool) filter_var($email, FILTER_VALIDATE_EMAIL)));
        return $emails;
    }

    /**
     * @param array{indexers: array<int, array<string, mixed>>, mviews: array<int, array<string, mixed>>} $issues
     */
    private function buildContent(array $issues): string
    {
        $lines = [];
        $lines[] = 'Indexers / Materialized Views monitoring alert';
        $lines[] = '';

        if (!empty($issues['indexers'])) {
            $lines[] = 'Problematic indexers:';
            foreach ($issues['indexers'] as $item) {
                $lines[] = sprintf(
                    '- %s | status=%s | updated=%s | threshold=%d min',
                    $item['id'] ?? 'n/a',
                    $item['status'] ?? 'n/a',
                    $item['updated_at'] ?? 'n/a',
                    (int) ($item['threshold_minutes'] ?? 0)
                );
            }
            $lines[] = '';
        }

        if (!empty($issues['mviews'])) {
            $lines[] = 'Problematic MViews (Materialized Views):';
            foreach ($issues['mviews'] as $item) {
                $lines[] = sprintf(
                    '- %s | status=%s | mode=%s | updated=%s%s',
                    $item['id'] ?? 'n/a',
                    $item['status'] ?? 'n/a',
                    $item['mode'] ?? 'n/a',
                    $item['updated_at'] ?? 'n/a',
                    isset($item['threshold_minutes']) ? sprintf(' | threshold=%d min', (int) $item['threshold_minutes']) : ''
                );
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
