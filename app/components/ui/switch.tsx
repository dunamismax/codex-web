import { cn } from "@/lib/utils";

type SwitchProps = {
  checked: boolean;
  onCheckedChange: (next: boolean) => void;
  disabled?: boolean;
  label: string;
};

export function Switch({ checked, onCheckedChange, disabled, label }: SwitchProps) {
  return (
    <label className="flex items-center justify-between gap-3 text-sm text-zinc-200">
      <span>{label}</span>
      <button
        aria-pressed={checked}
        className={cn(
          "relative h-6 w-11 rounded-full border border-zinc-700 transition",
          checked ? "bg-zinc-200" : "bg-zinc-900",
        )}
        disabled={disabled}
        onClick={() => onCheckedChange(!checked)}
        type="button"
      >
        <span
          className={cn(
            "absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-zinc-950 transition",
            checked ? "translate-x-5" : "translate-x-0",
          )}
        />
      </button>
    </label>
  );
}
