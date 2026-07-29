import { createTheme, ThemeProvider } from '@mui/material';
import { useMemo } from 'react';
import type { ReactNode } from 'react';
import { useAppearance } from '@/hooks/use-appearance';

export default function UsersTableThemeProvider({
    children,
}: {
    children: ReactNode;
}) {
    const { resolvedAppearance } = useAppearance();

    const theme = useMemo(
        () =>
            createTheme({
                palette: { mode: resolvedAppearance },
                typography: { fontFamily: 'inherit' },
            }),
        [resolvedAppearance],
    );

    return <ThemeProvider theme={theme}>{children}</ThemeProvider>;
}
