<?php

declare(strict_types=1);

namespace App\Enum;

enum OptimizationStepCategory: string
{
    case VEHICLE_MAPPING = 'vehicle_mapping';
    case JOB_MAPPING = 'job_mapping';
    case OPTIMIZER_SELECTION = 'optimizer_selection';
    case OPTIMIZER_CALL = 'optimizer_call';
    case CAPACITY_CHECK = 'capacity_check';
    case DISTANCE_CALCULATION = 'distance_calculation';
    case STOP_ORDERING = 'stop_ordering';
    case UNASSIGNED_JOBS = 'unassigned_jobs';
    case TIME_WINDOWS = 'time_windows';
    case SKILLS_MATCHING = 'skills_matching';
    case RESULT_SUMMARY = 'result_summary';
    case TIMING_ESTIMATION = 'timing_estimation';

    public function label(): string
    {
        return match ($this) {
            self::VEHICLE_MAPPING => 'Mapeo de vehiculos',
            self::JOB_MAPPING => 'Mapeo de trabajos',
            self::OPTIMIZER_SELECTION => 'Seleccion de optimizador',
            self::OPTIMIZER_CALL => 'Llamada al optimizador',
            self::CAPACITY_CHECK => 'Validacion de capacidad',
            self::DISTANCE_CALCULATION => 'Calculo de distancia',
            self::STOP_ORDERING => 'Ordenacion de paradas',
            self::UNASSIGNED_JOBS => 'Trabajos sin asignar',
            self::TIME_WINDOWS => 'Ventanas horarias',
            self::SKILLS_MATCHING => 'Matching de habilidades',
            self::RESULT_SUMMARY => 'Resumen de resultado',
            self::TIMING_ESTIMATION => 'Estimacion de tiempos',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::VEHICLE_MAPPING => 'truck',
            self::JOB_MAPPING => 'package',
            self::OPTIMIZER_SELECTION => 'cpu',
            self::OPTIMIZER_CALL => 'zap',
            self::CAPACITY_CHECK => 'scale',
            self::DISTANCE_CALCULATION => 'ruler',
            self::STOP_ORDERING => 'list-ordered',
            self::UNASSIGNED_JOBS => 'alert-triangle',
            self::TIME_WINDOWS => 'clock',
            self::SKILLS_MATCHING => 'check-circle',
            self::RESULT_SUMMARY => 'bar-chart',
            self::TIMING_ESTIMATION => 'timer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::VEHICLE_MAPPING => 'blue',
            self::JOB_MAPPING => 'indigo',
            self::OPTIMIZER_SELECTION => 'purple',
            self::OPTIMIZER_CALL => 'yellow',
            self::CAPACITY_CHECK => 'orange',
            self::DISTANCE_CALCULATION => 'cyan',
            self::STOP_ORDERING => 'green',
            self::UNASSIGNED_JOBS => 'red',
            self::TIME_WINDOWS => 'teal',
            self::SKILLS_MATCHING => 'emerald',
            self::RESULT_SUMMARY => 'gray',
            self::TIMING_ESTIMATION => 'violet',
        };
    }
}
