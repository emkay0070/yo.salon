'use client';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

interface TrendChartProps {
  data: any[];
  dataKey: string;
  dataKeySecondary?: string;
  xAxisKey: string;
  height?: number;
  formatTooltip?: (value: number) => string;
}

export function TrendChart({ data, dataKey, dataKeySecondary, xAxisKey, height = 300, formatTooltip }: TrendChartProps) {
  return (
    <div style={{ height: `${height}px` }} className="w-full">
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={data} margin={{ top: 10, right: 0, left: -20, bottom: 0 }}>
          <defs>
            <linearGradient id="colorValue" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="#6C5CE7" stopOpacity={0.3}/>
              <stop offset="95%" stopColor="#6C5CE7" stopOpacity={0}/>
            </linearGradient>
            {dataKeySecondary && (
              <linearGradient id="colorValueSecondary" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="#A29BFE" stopOpacity={0.3}/>
                <stop offset="95%" stopColor="#A29BFE" stopOpacity={0}/>
              </linearGradient>
            )}
          </defs>
          <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border-medium)" vertical={false} />
          <XAxis 
            dataKey={xAxisKey} 
            stroke="#606060" 
            tick={{ fill: '#606060', fontSize: 12 }} 
            tickLine={false} 
            axisLine={false} 
            dy={10}
          />
          <YAxis 
            stroke="#606060" 
            tick={{ fill: '#606060', fontSize: 12 }} 
            tickLine={false} 
            axisLine={false}
            tickFormatter={(value) => value >= 1000 ? `${(value/1000).toFixed(0)}k` : value}
          />
          <Tooltip 
            contentStyle={{ 
              backgroundColor: 'var(--color-card)', 
              borderRadius: '12px', 
              border: '1px solid var(--color-border-medium)',
              backdropFilter: 'blur(12px)',
              color: 'var(--color-text-primary)'
            }}
            itemStyle={{ color: '#FFD700' }}
            formatter={(value: any) => [formatTooltip ? formatTooltip(value) : value, '']}
            labelStyle={{ color: 'var(--color-text-secondary)', marginBottom: '4px' }}
          />
          <Area 
            type="monotone" 
            dataKey={dataKey} 
            stroke="#6C5CE7" 
            strokeWidth={2}
            fillOpacity={1} 
            fill="url(#colorValue)" 
            name="Gross"
          />
          {dataKeySecondary && (
            <Area 
              type="monotone" 
              dataKey={dataKeySecondary} 
              stroke="#A29BFE" 
              strokeWidth={2}
              fillOpacity={1} 
              fill="url(#colorValueSecondary)" 
              name="Net"
            />
          )}
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}
