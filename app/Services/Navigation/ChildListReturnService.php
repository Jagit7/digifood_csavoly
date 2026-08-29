<?php

namespace App\Services\Navigation;

use App\Models\Child;
use App\Models\DietaryRestriction;
use App\Models\DiscountType;
use App\Models\Institution;

class ChildListReturnService
{
    public const LIST_BASICS = 'basics';

    public const LIST_CHILDREN = 'children';

    public const LIST_EATERS = 'eaters';

    private const BASICS_QUALITY_VALUES = [
        'any_issue',
        'no_guardian',
        'no_email',
        'no_payer',
        'not_activated',
        'no_meal_setting',
        'no_group',
        'no_bank_account',
    ];

    private const CHILDREN_DATA_QUALITY_VALUES = [
        'any_issue',
        'no_guardian',
        'no_billing',
        'no_email',
        'no_phone',
        'no_address',
    ];

    private const STATUS_VALUES = ['active', 'inactive'];

    private const VERIFIED_STATUS_VALUES = ['verified', 'unverified'];

    private const MEAL_STATUS_VALUES = ['participant', 'non_participant'];

    private const BASICS_SORT_VALUES = ['class', 'name'];

    public function sanitizeListId(?string $listId): ?string
    {
        return in_array($listId, [
            self::LIST_BASICS,
            self::LIST_CHILDREN,
            self::LIST_EATERS,
        ], true) ? $listId : null;
    }

    public function routeNameFor(string $listId): string
    {
        return match ($listId) {
            self::LIST_BASICS => 'dashboard.institution.billing-addresses.index',
            self::LIST_EATERS => 'dashboard.institution.children.meal-participants.index',
            default => 'dashboard.institution.children.index',
        };
    }

    public function buildUrl(string $listId, array $params = []): string
    {
        return route($this->routeNameFor($listId), $params);
    }

    public function currentReturnState(string $listId, array $query, Institution $institution): array
    {
        $listId = $this->sanitizeListId($listId) ?? self::LIST_CHILDREN;
        $params = $this->sanitizeQueryArray($listId, $query, $institution);

        return [
            'list' => $listId,
            'route' => $this->routeNameFor($listId),
            'params' => $params,
            'query' => $this->buildQuery($params),
            'url' => $this->buildUrl($listId, $params),
        ];
    }

    public function resolveReturnDestination(
        ?string $returnList,
        string $returnQuery,
        Institution $institution,
        string $defaultList = self::LIST_CHILDREN
    ): array {
        $listId = $this->sanitizeListId($returnList) ?? $defaultList;
        $params = $this->sanitizeEncodedQuery($listId, $returnQuery, $institution);

        return [
            'list' => $listId,
            'route' => $this->routeNameFor($listId),
            'params' => $params,
            'query' => $this->buildQuery($params),
            'url' => $this->buildUrl($listId, $params),
        ];
    }

    public function sanitizeEncodedQuery(string $listId, string $query, Institution $institution): array
    {
        $sanitizedQuery = $this->sanitizeRawQuery($query);

        if ($sanitizedQuery === '') {
            return [];
        }

        parse_str($sanitizedQuery, $params);

        return $this->sanitizeQueryArray($listId, $params, $institution);
    }

    public function buildQuery(array $params): string
    {
        return http_build_query($params);
    }

    private function sanitizeRawQuery(string $query): string
    {
        $query = preg_replace('/[\r\n]+/', '', $query);
        $query = preg_replace('/[\x00-\x1F\x7F]+/u', '', (string) $query);

        return trim((string) $query);
    }

    private function sanitizeQueryArray(string $listId, array $params, Institution $institution): array
    {
        return match ($listId) {
            self::LIST_BASICS => $this->sanitizeBasicsParams($params, $institution),
            self::LIST_EATERS => $this->sanitizeEatersParams($params, $institution),
            default => $this->sanitizeChildrenParams($params, $institution),
        };
    }

    private function sanitizeBasicsParams(array $params, Institution $institution): array
    {
        $discountIds = $this->discountIds($institution);
        $restrictionIds = $this->dietaryRestrictionIds($institution);
        $childIds = $this->childIds($institution);

        return array_filter([
            'page' => $this->sanitizePositiveInt($params['page'] ?? null),
            'search' => $this->sanitizeText($params['search'] ?? null),
            'sort' => $this->sanitizeEnum($params['sort'] ?? null, self::BASICS_SORT_VALUES),
            'quality' => $this->sanitizeEnum($params['quality'] ?? null, self::BASICS_QUALITY_VALUES),
            'discount_type_id' => $this->sanitizeDiscountLike($params['discount_type_id'] ?? null, $discountIds),
            'dietary_restriction_id' => $this->sanitizeDiscountLike($params['dietary_restriction_id'] ?? null, $restrictionIds),
            'opened_child' => $this->sanitizeInstitutionId($params['opened_child'] ?? null, $childIds),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function sanitizeChildrenParams(array $params, Institution $institution): array
    {
        $groupNames = $this->groupNames($institution);

        return array_filter([
            'page' => $this->sanitizePositiveInt($params['page'] ?? null),
            'search' => $this->sanitizeText($params['search'] ?? null),
            'group_name' => $this->sanitizeEnum($params['group_name'] ?? null, $groupNames),
            'status' => $this->sanitizeEnum($params['status'] ?? null, self::STATUS_VALUES),
            'data_quality' => $this->sanitizeEnum($params['data_quality'] ?? null, self::CHILDREN_DATA_QUALITY_VALUES),
            'verified_status' => $this->sanitizeEnum($params['verified_status'] ?? null, self::VERIFIED_STATUS_VALUES),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function sanitizeEatersParams(array $params, Institution $institution): array
    {
        $groupNames = $this->groupNames($institution);
        $discountIds = $this->discountIds($institution);
        $restrictionIds = $this->dietaryRestrictionIds($institution);

        return array_filter([
            'page' => $this->sanitizePositiveInt($params['page'] ?? null),
            'search' => $this->sanitizeText($params['search'] ?? null),
            'group_name' => $this->sanitizeEnum($params['group_name'] ?? null, $groupNames),
            'meal_status' => $this->sanitizeEnum($params['meal_status'] ?? null, self::MEAL_STATUS_VALUES),
            'status' => $this->sanitizeEnum($params['status'] ?? null, self::STATUS_VALUES),
            'diet_filter' => $this->sanitizeDietFilter($params['diet_filter'] ?? null, $restrictionIds),
            'discount_filter' => $this->sanitizeInstitutionId($params['discount_filter'] ?? null, $discountIds),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function sanitizePositiveInt(mixed $value): ?int
    {
        if (! is_scalar($value) || ! preg_match('/^[1-9][0-9]*$/', (string) $value)) {
            return null;
        }

        return (int) $value;
    }

    private function sanitizeText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = $this->sanitizeRawQuery((string) $value);

        return $value === '' ? null : $value;
    }

    private function sanitizeEnum(mixed $value, array $allowed): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function sanitizeInstitutionId(mixed $value, array $allowedIds): ?int
    {
        if (! is_scalar($value) || ! preg_match('/^[1-9][0-9]*$/', (string) $value)) {
            return null;
        }

        $value = (int) $value;

        return in_array($value, $allowedIds, true) ? $value : null;
    }

    private function sanitizeDiscountLike(mixed $value, array $allowedIds): string|int|null
    {
        if ($value === 'any') {
            return 'any';
        }

        return $this->sanitizeInstitutionId($value, $allowedIds);
    }

    private function sanitizeDietFilter(mixed $value, array $allowedRestrictionIds): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if (in_array($value, ['any_allergen', 'any_intolerance'], true)) {
            return $value;
        }

        if (preg_match('/^restriction_([1-9][0-9]*)$/', $value, $matches) !== 1) {
            return null;
        }

        $restrictionId = (int) $matches[1];

        return in_array($restrictionId, $allowedRestrictionIds, true)
            ? 'restriction_'.$restrictionId
            : null;
    }

    private function groupNames(Institution $institution): array
    {
        return Child::query()
            ->where('institution_id', $institution->id)
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->pluck('group_name')
            ->all();
    }

    private function discountIds(Institution $institution): array
    {
        return DiscountType::query()
            ->where('institution_id', $institution->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function dietaryRestrictionIds(Institution $institution): array
    {
        return DietaryRestriction::query()
            ->where('institution_id', $institution->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function childIds(Institution $institution): array
    {
        return Child::query()
            ->where('institution_id', $institution->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
