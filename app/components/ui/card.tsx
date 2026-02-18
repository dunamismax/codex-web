import { cn } from "@/lib/utils";

export function Card({ className, ...props }: React.ComponentProps<"section">) {
  return (
    <section
      className={cn(
        "rounded-xl border border-zinc-800 bg-zinc-950/80 p-4 shadow-[0_0_0_1px_rgba(255,255,255,0.03)]",
        className,
      )}
      {...props}
    />
  );
}
