import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

type QuickLoginRole = {
    name: string;
    url: string;
};

type QuickLoginProps = {
    quickLogin?: {
        roles: QuickLoginRole[];
    };
};

export default function QuickLogin() {
    const { quickLogin } = usePage<QuickLoginProps>().props;

    if (!quickLogin || quickLogin.roles.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-4">
            <div className="relative">
                <div className="absolute inset-0 flex items-center">
                    <span className="w-full border-t border-border" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                    <span className="bg-background px-2 text-muted-foreground">
                        Accesso rapido (dev)
                    </span>
                </div>
            </div>
            <div className="grid gap-2">
                {quickLogin.roles.map((role) => (
                    <Button
                        key={role.name}
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => router.post(role.url)}
                        data-test={`quick-login-${role.name}`}
                    >
                        {role.name}
                    </Button>
                ))}
            </div>
        </div>
    );
}
