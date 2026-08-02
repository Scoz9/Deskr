import { Link } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    FolderGit2,
    LayoutGrid,
    Mail,
    Shield,
    Ticket,
    Users,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCan } from '@/hooks/use-can';
import { dashboard } from '@/routes';
import { index as notificationsIndex } from '@/routes/notifications';
import { index as organizationsIndex } from '@/routes/organizations';
import { index as rolesIndex } from '@/routes/roles';
import { index as teamsIndex } from '@/routes/teams';
import { index as ticketsIndex } from '@/routes/tickets';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const can = useCan();

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Ticket',
            href: ticketsIndex(),
            icon: Ticket,
            canView: can('ticket:viewAny'),
        },
        {
            title: 'Ruoli',
            href: rolesIndex(),
            icon: Shield,
            canView: can('role:viewAny'),
        },
        {
            title: 'Utenti',
            href: usersIndex(),
            icon: Users,
            canView: can('user:viewAny'),
        },
        {
            title: 'Organizzazioni',
            href: organizationsIndex(),
            icon: Building2,
            canView: can('organization:viewAny'),
        },
        {
            title: 'Team',
            href: teamsIndex(),
            icon: UsersRound,
            canView: can('team:viewAny'),
        },
        {
            title: 'Notifiche',
            href: notificationsIndex(),
            icon: Mail,
            canView: can('notification:viewAny'),
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
