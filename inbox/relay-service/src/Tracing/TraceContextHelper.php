<?php

declare(strict_types=1);

namespace InboxRelay\Tracing;

use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;

class TraceContextHelper
{
    public static function serializeFromSpan(SpanInterface $span): string
    {
        $spanContext = $span->getContext();

        if (!$spanContext->isValid()) {
            return '';
        }

        return sprintf(
            '00-%s-%s-%s',
            $spanContext->getTraceId(),
            $spanContext->getSpanId(),
            $spanContext->isSampled() ? '01' : '00'
        );
    }

    public static function extractParentContext(string $traceparent): ?ContextInterface
    {
        if (empty($traceparent)) {
            return null;
        }

        $parts = explode('-', $traceparent);

        if (count($parts) !== 4) {
            return null;
        }

        [$version, $traceId, $spanId, $traceFlags] = $parts;

        if ($version !== '00' || strlen($traceId) !== 32 || strlen($spanId) !== 16) {
            return null;
        }

        $flags = hexdec($traceFlags) === 1 ? TraceFlags::SAMPLED : TraceFlags::DEFAULT;

        $remoteSpanContext = SpanContext::createFromRemoteParent(
            $traceId,
            $spanId,
            $flags
        );

        if (!$remoteSpanContext->isValid()) {
            return null;
        }

        return Context::getCurrent()->withContextValue(
            Span::wrap($remoteSpanContext)
        );
    }
}
