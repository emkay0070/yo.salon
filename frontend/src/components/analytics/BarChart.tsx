'use client';
import { BarChart as RechartsBarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell } from 'recharts';

interface BarChartProps {
  data: any[];
  dataKey: string;
  xAxisKey: string;
  height?: number;
  formatTooltip?: (value: number) => string;
  layout?: 'horizontal' | 'vertical';
}

export function BarChart({ data, dataKey, xAxisKey, height = 300, formatTooltip, layout = 'horizontal' }: BarChartProps) {
  const isVertical = layout === 'vertical';
  return (
    <div style={{ height: `${height}px` }} className="w-full">
      <ResponsiveContainer width="100%" height="100%">
        <RechartsBarChart 
          data={data} 
          layout={layout}
          margin={isVertical ? { top: 0, right: 20, left: 20, bottom: 0 } : { top: 10, right: 0, left: -20, bottom: 0 }}
        >
          <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border-medium)" horizontal={!isVertical} vertical={isVertical} />
          <XAxis 
            type={isVertical ? "number" : "category"} 
            dataKey={isVertical ? undefined : xAxisKey} 
            stroke="#606060" 
            tick={{ fill: '#606060', fontSize: 12 }} 
            tickLine={false} 
            axisLine={false}
            dy={isVertical ? 0 : 10}
            hide={isVertical}
          />
          <YAxis 
            type={isVertical ? "category" : "number"} 
            dataKey={isVertical ? xAxisKey : undefined} 
            stroke="#606060" 
            tick={{ fill: '#606060', fontSize: 12 }} 
            tickLine={false} 
            axisLine={false}
            tickFormatter={(value) => typeof value === 'number' && value >= 1000 ? `${(value/1000).toFixed(0)}k` : value}
            width={isVertical ? 80 : 40}
          />
          <Tooltip 
            contentStyle={{ 
              backgroundColor: 'var(--color-card)', 
              borderRadius: '12px', 
              border: '1px solid var(--color-border-medium)',
              backdropFilter: 'blur(12px)',
              color: 'var(--color-text-primary)'
            }}
            cursor={{ fill: 'var(--color-border-light)' }}
            formatter={(value: any) => [formatTooltip ? formatTooltip(value) : value, '']}
            labelStyle={{ color: 'var(--color-text-secondary)', marginBottom: '4px' }}
          />
          <Bar dataKey={dataKey} radius={[4, 4, 4, 4]} barSize={isVertical ? 24 : 32}>
             {data.map((entry, index) => (
                <Cell key={`cell-${index}`} fill={index === 0 ? '#FFD700' : 'rgba(255,215,0,0.4)'} />
             ))}
          </Bar>
        </RechartsBarChart>
      </ResponsiveContainer>
    </div>
  );
}
