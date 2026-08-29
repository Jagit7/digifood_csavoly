@if($mealSetting->mode === \App\Models\StudentMealSetting::MODE_INSTITUTION_DEFAULT)
    <span>Intézményi alapértelmezett</span>
    @if($defaultPackage)
        <div class="small text-muted">{{ $defaultPackage->name }}</div>
    @endif
@elseif($mealSetting->mode === \App\Models\StudentMealSetting::MODE_PACKAGE)
    <span>{{ $mealSetting->mealPackage?->name ?? '—' }}</span>
@else
    @if($mealSetting->mealTypes->count())
        {{ $mealSetting->mealTypes->map(fn ($mealType) => $mealType->mealType->name)->implode(' + ') }}
    @else
        <span class="text-muted">—</span>
    @endif
@endif
