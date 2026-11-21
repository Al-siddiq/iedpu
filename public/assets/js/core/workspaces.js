/**
 * Static copy for read-only P1 interface previews.
 * These objects are NOT users, privileges, branches or member records.
 */
const feature = (title, description) => ({ title, description });

export const workspaces = Object.freeze({
    '/': { title: 'Public home' },
    '/preview/member': {
        title: 'Member portal',
        overline: 'A PLACE FOR EVERY MEMBER',
        description: 'A simple, personal space for your membership journey.',
        welcome: 'Your membership, all in one place.',
        guidance: 'When the portal opens, you will be able to review your own information and follow membership updates without visiting an office for every question.',
        action: 'Sign in to my account',
        next: 'Member account setup and secure sign-in will be introduced after identity and registration controls are approved.',
        features: [
            feature('My profile', 'View and request corrections to your personal membership information.'),
            feature('My dues', 'See confirmed dues information when the finance module is approved.'),
            feature('Announcements', 'Receive relevant information from authorised IEDPU officers.'),
            feature('Membership information', 'Access guidance and approved documents in one place.'),
        ],
    },
    '/preview/branch': {
        title: 'Branch administration',
        overline: 'SUPPORTING BRANCH LEADERS',
        description: 'A clearer way to review membership and coordinate branch work.',
        welcome: 'Help your branch work with confidence.',
        guidance: 'Authorised branch officers will have a focused view of the tasks they are permitted to carry out. The branch chairman retains the required verification responsibility.',
        action: 'Open branch workspace',
        next: 'Branches, office holders and role assignments will be entered through authorised administration interfaces; no branch names or officers are built into the code.',
        features: [
            feature('Membership review', 'Review submitted member information within the assigned branch.'),
            feature('Verification requests', 'Track requests and decisions with a clear history.'),
            feature('Branch records', 'Maintain branch information through approved processes.'),
            feature('Reports', 'View relevant summaries within assigned permissions.'),
        ],
    },
    '/preview/committee': {
        title: 'Committee administration',
        overline: 'OVERSIGHT WITH ACCOUNTABILITY',
        description: 'Tools for authorised Committee officers to guide the portal.',
        welcome: 'Make administration easier to follow.',
        guidance: 'Committee officers will manage approved organisational information, delegated responsibilities and decisions, with clear records of who acted and when.',
        action: 'Open Committee workspace',
        next: 'The first Committee Super Administrator will be securely provisioned in P2. That administrator may directly activate a validated branch, with evidence and audit.',
        features: [
            feature('Organisation setup', 'Create and maintain zones, branches, offices and office-holder records.'),
            feature('Role administration', 'Assign controlled portal permissions without hardcoded role profiles.'),
            feature('Approvals and oversight', 'Review authorised requests and see decision histories.'),
            feature('Registration settings', 'Approve versioned registration rules and privacy notices before activation.'),
        ],
    },
    '/preview/technical': {
        title: 'Technical administration',
        overline: 'A SEPARATE DEVELOPER SPACE',
        description: 'Operational tools for the authorised technical team.',
        welcome: 'Keep the platform healthy and secure.',
        guidance: 'Developers and technical operators will have a separate workspace for platform maintenance. Technical access will not grant Committee business powers.',
        action: 'Open technical workspace',
        next: 'The technical operator interface, provisioning and audit controls will be implemented with the authentication and permission foundation in P2.',
        features: [
            feature('System diagnostics', 'Review platform health and configuration safely.'),
            feature('Maintenance', 'Manage deployments, backups and planned maintenance tasks.'),
            feature('Security operations', 'Investigate technical issues without exposing member data unnecessarily.'),
            feature('Audit support', 'Support traceable, authorised operational actions.'),
        ],
    },
});

export const navigationItems = Object.freeze([
    { path: '/', label: 'Public home', shortLabel: 'Home', icon: 'house-door', preview: false },
    { path: '/preview/member', label: 'Member portal', shortLabel: 'Member', icon: 'person-circle', preview: true },
    { path: '/preview/branch', label: 'Branch administration', shortLabel: 'Branch', icon: 'people', preview: true },
    { path: '/preview/committee', label: 'Committee administration', shortLabel: 'Committee', icon: 'building', preview: true },
    { path: '/preview/technical', label: 'Technical administration', shortLabel: 'Technical', icon: 'gear', preview: true },
]);

export const previewCards = Object.freeze([
    { ...navigationItems[1], tone: 'tone-red', description: 'Your profile, updates and membership information.' },
    { ...navigationItems[2], tone: 'tone-teal', description: 'Verification, branch records and guided tasks.' },
    { ...navigationItems[3], tone: 'tone-navy', description: 'Organisational oversight, approvals and reporting.' },
    { ...navigationItems[4], tone: 'tone-gold', description: 'System maintenance, diagnostics and technical operations.' },
]);
