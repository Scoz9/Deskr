import { Form, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import AvatarController from '@/actions/App/Http/Controllers/Settings/AvatarController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/hooks/use-initials';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function UpdateAvatar() {
    const { auth } = usePage<PageProps>().props;
    const getInitials = useInitials();
    const fileInput = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);

    /**
     * Object URLs stay allocated until they are revoked, so release the
     * previous one whenever the selection changes or the section unmounts.
     */
    useEffect(() => {
        return () => {
            if (preview !== null) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    const clearSelection = () => {
        setPreview(null);

        if (fileInput.current !== null) {
            fileInput.current.value = '';
        }
    };

    const handleSelect = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        setPreview(file === undefined ? null : URL.createObjectURL(file));
    };

    const handleRemove = () => {
        router.delete(AvatarController.destroy.url(), {
            preserveScroll: true,
            onSuccess: clearSelection,
        });
    };

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Avatar"
                description="Upload a picture to personalise your account"
            />

            <Form
                {...AvatarController.update.form()}
                options={{
                    preserveScroll: true,
                }}
                onSuccess={clearSelection}
                className="space-y-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="flex items-center gap-6">
                            <Avatar className="size-20 overflow-hidden rounded-full">
                                <AvatarImage
                                    src={
                                        preview ?? auth.user.avatar ?? undefined
                                    }
                                    alt={auth.user.name}
                                />
                                <AvatarFallback className="rounded-full bg-neutral-200 text-lg text-black dark:bg-neutral-700 dark:text-white">
                                    {getInitials(auth.user.name)}
                                </AvatarFallback>
                            </Avatar>

                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="avatar">Picture</Label>

                                <Input
                                    id="avatar"
                                    type="file"
                                    name="avatar"
                                    ref={fileInput}
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={handleSelect}
                                />

                                <p className="text-sm text-muted-foreground">
                                    JPG, PNG or WEBP up to 5 MB. Larger pictures
                                    are scaled down to fit 512×512.
                                </p>

                                <InputError message={errors.avatar} />
                            </div>
                        </div>

                        <div className="flex items-center gap-4">
                            <Button
                                disabled={processing || preview === null}
                                data-test="update-avatar-button"
                            >
                                Save
                            </Button>

                            {auth.user.avatar != null && (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={handleRemove}
                                    data-test="remove-avatar-button"
                                >
                                    Remove
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
