<?php

namespace Equidna\DomainTokenAuth\Support;

class ActionMatcher
{
    /**
     * @param array<int, string> $grantedActions
     */
    public static function allows(array $grantedActions, string $requiredAction): bool
    {
        $requiredAction = self::normalize($requiredAction);

        if ($requiredAction === '') {
            return false;
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            fn(string $action): string => self::normalize($action),
            $grantedActions
        ))));

        if (in_array('*', $normalized, true)) {
            return true;
        }

        if (in_array($requiredAction, $normalized, true)) {
            return true;
        }

        $segments = explode('.', $requiredAction);
        if (count($segments) > 1) {
            $domainWildcard = $segments[0] . '.*';
            return in_array($domainWildcard, $normalized, true);
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public static function parseCsv(string $csv): array
    {
        $parts = array_map('trim', explode(',', $csv));
        $parts = array_filter($parts, fn(string $part): bool => $part !== '');

        return array_values(array_unique(array_map(
            fn(string $part): string => self::normalize($part),
            $parts
        )));
    }

    public static function normalize(string $action): string
    {
        return strtolower(trim($action));
    }
}
