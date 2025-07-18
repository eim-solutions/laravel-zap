<?php

namespace Zap\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use BusinessPress\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $schedule_id
 * @property Carbon $date
 * @property Carbon|null $start_time
 * @property Carbon|null $end_time
 * @property bool $is_available
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Schedule $schedule
 * @property-read int $duration_minutes
 * @property-read Carbon $start_date_time
 * @property-read Carbon $end_date_time
 */
class SchedulePeriod extends BaseModel
{
    use HasUuids;

    protected $table = 'schedule_periods';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'schedule_id',
        'date',
        'start_time',
        'end_time',
        'is_available',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
        'is_available' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the schedule that owns the period.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Get the duration in minutes.
     */
    public function getDurationMinutesAttribute(): int
    {
        if (! $this->start_time || ! $this->end_time) {
            return 0;
        }

        $baseDate = '2024-01-01'; // Use a consistent base date for time parsing
        $start = Carbon::parse($baseDate.' '.$this->start_time);
        $end = Carbon::parse($baseDate.' '.$this->end_time);

        return (int) $start->diffInMinutes($end);
    }

    /**
     * Get the full start datetime.
     */
    public function getStartDateTimeAttribute(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d').' '.$this->start_time);
    }

    /**
     * Get the full end datetime.
     */
    public function getEndDateTimeAttribute(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d').' '.$this->end_time);
    }

    /**
     * Check if this period overlaps with another period.
     */
    public function overlapsWith(SchedulePeriod $other): bool
    {
        // Must be on the same date
        if (! $this->date->eq($other->date)) {
            return false;
        }

        return $this->start_time < $other->end_time && $this->end_time > $other->start_time;
    }

    /**
     * Check if this period is currently active (happening now).
     */
    public function isActiveNow(): bool
    {
        $now = Carbon::now();
        $startDateTime = $this->start_date_time;
        $endDateTime = $this->end_date_time;

        return $now->between($startDateTime, $endDateTime);
    }

    /**
     * Scope a query to only include available periods.
     */
    public function scopeAvailable(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope a query to only include periods for a specific date.
     */
    public function scopeForDate(\Illuminate\Database\Eloquent\Builder $query, string $date): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('date', $date);
    }

    /**
     * Scope a query to only include periods within a time range.
     */
    public function scopeForTimeRange(\Illuminate\Database\Eloquent\Builder $query, string $startTime, string $endTime): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('start_time', '>=', $startTime)
            ->where('end_time', '<=', $endTime);
    }

    /**
     * Scope a query to find overlapping periods.
     */
    public function scopeOverlapping(\Illuminate\Database\Eloquent\Builder $query, string $date, string $startTime, string $endTime): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereDate('date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);
    }

    /**
     * Convert the period to a human-readable string.
     */
    public function __toString(): string
    {
        return sprintf(
            '%s from %s to %s',
            $this->date->format('Y-m-d'),
            $this->start_time,
            $this->end_time
        );
    }
}
