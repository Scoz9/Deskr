import { Button } from '@/components/ui/button';
import type { Placeholder } from '@/lib/notification-kit/types';

type PlaceholderPaletteProps = {
    placeholders: Placeholder[];
    onInsert: (token: string) => void;
};

export default function PlaceholderPalette({
    placeholders,
    onInsert,
}: PlaceholderPaletteProps) {
    if (placeholders.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Questo contenuto non dichiara segnaposto.
            </p>
        );
    }

    return (
        <ul className="flex flex-col gap-2">
            {placeholders.map((placeholder) => (
                <li key={placeholder.key}>
                    <Button
                        type="button"
                        variant="outline"
                        className="h-auto w-full flex-col items-start gap-0.5 px-3 py-2 text-left"
                        onClick={() => onInsert(`{{ ${placeholder.key} }}`)}
                    >
                        <code className="text-xs font-medium">{`{{ ${placeholder.key} }}`}</code>
                        <span className="text-xs font-normal text-muted-foreground">
                            {placeholder.description}
                        </span>
                    </Button>
                </li>
            ))}
        </ul>
    );
}
