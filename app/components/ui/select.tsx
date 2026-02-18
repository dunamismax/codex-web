import * as SelectPrimitive from "@radix-ui/react-select";

import { cn } from "@/lib/utils";

type SelectOption = { value: string; label: string };

type SelectProps = {
  value: string;
  onChange: (value: string) => void;
  options: SelectOption[];
  disabled?: boolean;
};

export function Select({ value, onChange, options, disabled }: SelectProps) {
  return (
    <SelectPrimitive.Root disabled={disabled} onValueChange={onChange} value={value}>
      <SelectPrimitive.Trigger
        className={cn(
          "flex h-10 w-full items-center justify-between rounded-md border border-zinc-800 bg-zinc-950 px-3 text-sm text-zinc-100 focus:outline-none focus:ring-2 focus:ring-zinc-300/30",
        )}
      >
        <SelectPrimitive.Value />
      </SelectPrimitive.Trigger>
      <SelectPrimitive.Portal>
        <SelectPrimitive.Content className="z-50 min-w-[var(--radix-select-trigger-width)] overflow-hidden rounded-md border border-zinc-700 bg-zinc-950 shadow-xl">
          <SelectPrimitive.Viewport className="p-1">
            {options.map((option) => (
              <SelectPrimitive.Item
                className="cursor-pointer rounded px-2 py-1.5 text-sm text-zinc-200 outline-none data-[highlighted]:bg-zinc-900"
                key={option.value}
                value={option.value}
              >
                <SelectPrimitive.ItemText>{option.label}</SelectPrimitive.ItemText>
              </SelectPrimitive.Item>
            ))}
          </SelectPrimitive.Viewport>
        </SelectPrimitive.Content>
      </SelectPrimitive.Portal>
    </SelectPrimitive.Root>
  );
}
