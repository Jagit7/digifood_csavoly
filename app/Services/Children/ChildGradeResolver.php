<?php

namespace App\Services\Children;

use App\Models\Child;
use App\Models\ClassGroup;
use App\Models\Institution;
use Illuminate\Support\Collection;

class ChildGradeResolver
{
    public function resolveForChildren(Institution $institution, Collection $children): Collection
    {
        $groupNames = $children->pluck('group_name')->filter()->unique()->values();
        $schoolYears = $children->pluck('school_year')->filter()->unique()->values();
        $groups = ClassGroup::query()
            ->where('institution_id', $institution->id)
            ->whereIn('name', $groupNames)
            ->when(
                $schoolYears->isNotEmpty(),
                fn ($query) => $query->whereHas('schoolYear', fn ($schoolYearQuery) => $schoolYearQuery->whereIn('name', $schoolYears))
            )
            ->with('schoolYear:id,name')
            ->get();

        $byExactKey = $groups->keyBy(fn ($group) => $this->groupKey($group->schoolYear?->name, $group->name));
        $byGroupName = $groups->groupBy('name');

        return $children->mapWithKeys(function (Child $child) use ($byExactKey, $byGroupName) {
            $grade = null;
            $label = null;

            if (filled($child->group_name)) {
                $exactMatch = $byExactKey->get($this->groupKey($child->school_year, $child->group_name));
                $fallbackMatch = $byGroupName->get($child->group_name)?->count() === 1
                    ? $byGroupName->get($child->group_name)?->first()
                    : null;
                $match = $exactMatch ?? $fallbackMatch;

                if ($match && $match->grade_level !== null) {
                    $grade = (int) $match->grade_level;
                    $label = (string) $match->grade_level;
                }
            }

            if ($grade === null && filled($child->group_name) && preg_match('/^\s*(\d{1,2})\b/u', (string) $child->group_name, $matches)) {
                $grade = (int) $matches[1];
                $label = $matches[1];
            }

            return [(int) $child->id => [
                'grade' => $grade,
                'grade_label' => $label,
            ]];
        });
    }

    private function groupKey(?string $schoolYear, ?string $groupName): string
    {
        return trim((string) $schoolYear).'|'.trim((string) $groupName);
    }
}
