interface RadialGaugeProps {
  value: number;
  max: number;
  size?: number;
  strokeWidth?: number;
  warnThreshold?: number;
  critThreshold?: number;
  label?: string;
  unit?: string;
  className?: string;
}

export function RadialGauge({
  value,
  max,
  size = 56,
  strokeWidth = 4,
  warnThreshold,
  critThreshold,
  label,
  unit = 'ms',
  className,
}: RadialGaugeProps) {
  const radius = (size - strokeWidth) / 2;
  const circumference = 2 * Math.PI * radius;
  const progress = Math.min(value / max, 1);
  const offset = circumference * (1 - progress);

  let color = 'var(--color-success)';
  if (critThreshold !== undefined && value > critThreshold) {
    color = 'var(--color-error)';
  } else if (warnThreshold !== undefined && value > warnThreshold) {
    color = 'var(--color-warning)';
  }

  return (
    <div className={className} style={{ position: 'relative', width: size, height: size, flexShrink: 0 }}>
      <svg
        width={size}
        height={size}
        viewBox={`0 0 ${size} ${size}`}
        style={{
          transform: 'rotate(-90deg)',
          ['--gauge-circumference' as string]: circumference,
          ['--gauge-offset' as string]: offset,
        }}
      >
        {/* Track */}
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          fill="none"
          stroke="var(--color-border)"
          strokeWidth={strokeWidth}
          opacity={0.3}
        />
        {/* Fill */}
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          fill="none"
          stroke={color}
          strokeWidth={strokeWidth}
          strokeDasharray={circumference}
          strokeDashoffset={offset}
          strokeLinecap="round"
          className="animate-gauge-fill"
          style={{ transition: 'stroke-dashoffset 0.8s ease-out, stroke 0.3s' }}
        />
      </svg>
      {/* Center text */}
      <div
        style={{
          position: 'absolute',
          inset: 0,
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
        }}
      >
        <span
          style={{
            fontSize: size > 48 ? '0.7rem' : '0.6rem',
            fontWeight: 700,
            fontFamily: 'var(--data-font)',
            color: 'var(--color-text-primary)',
            lineHeight: 1,
          }}
        >
          {value}
        </span>
        {(unit || label) && (
          <span
            style={{
              fontSize: '0.5rem',
              color: 'var(--color-text-muted)',
              lineHeight: 1,
              marginTop: 1,
            }}
          >
            {unit || label}
          </span>
        )}
      </div>
    </div>
  );
}
