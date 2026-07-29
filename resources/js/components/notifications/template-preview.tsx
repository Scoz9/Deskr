import { cn } from '@/lib/utils';

type TemplatePreviewProps = {
    html: string;
    className?: string;
};

/**
 * Renders the email HTML in a sandboxed iframe. The markup comes from the
 * package renderer and is already escaped; the sandbox keeps a preview from
 * ever running anything inside the admin page.
 */
export default function TemplatePreview({
    html,
    className,
}: TemplatePreviewProps) {
    return (
        <iframe
            title="Anteprima"
            sandbox=""
            srcDoc={html}
            className={cn(
                'w-full rounded-md border bg-white',
                className ?? 'h-96',
            )}
        />
    );
}
