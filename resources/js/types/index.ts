import type { Auth } from './auth';
import type { FlashMessage } from './ui';

export type * from './auth';
export type * from './navigation';
export type * from './ui';

export type SharedData = {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    fortify: FortifyFeatures;
    flashMessages: FlashMessage[];
};

export type FortifyFeatures = {
    registration: boolean;
    resetPasswords: boolean;
    twoFactorAuthentication: boolean;
    passkeys: boolean;
};

export type Permission = {
    id: number;
    name: string;
};

export type Role = {
    id: number;
    name: string;
    permissions: Permission[];
};

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: { id: number; name: string } | null;
    organization: { id: number; name: string } | null;
    is_suspended: boolean;
    suspended_at: string | null;
    suspended_until: string | null;
    email_verified_at: string | null;
    created_at: string;
    can_update: boolean;
    can_suspend: boolean;
};

export type AssignableRole = {
    id: number;
    name: string;
    hierarchy_rank: number;
};

export type Organization = {
    id: number;
    name: string;
    users_count: number;
};

export type Team = {
    id: number;
    name: string;
    categories_count: number;
    members_count: number;
    tickets_count: number;
};

/** A team as a category's form offers it: just enough to pick one. */
export type RoutableTeam = {
    id: number;
    name: string;
};

export type Category = {
    id: number;
    name: string;
    team: RoutableTeam;
    tickets_count: number;
};
