import { describe, expect, it } from 'vitest';
import { routes } from '../../resources/js/routes';

const cases: [keyof typeof routes, (string | number)[], string][] = [
    ['signIn', [], '/sign-in'],
    ['secondFactor', [], '/sign-in/second-factor'],
    ['signOut', [], '/sign-out'],
    ['newPassword', [], '/password/new'],
    ['account', [], '/account'],
    ['accountPassword', [], '/account/password'],
    ['accountSecondFactor', [], '/account/second-factor'],
    ['accountSecondFactorConfirm', [], '/account/second-factor/confirm'],
    ['accountRecoveryCodes', [], '/account/second-factor/recovery-codes'],
    ['keepAlive', [], '/session/keep-alive'],
    ['reauthenticate', [], '/session/reauthenticate'],
    ['overview', [], '/overview'],
    ['drawing', [3], '/canvases/3/drawing'],
    ['layout', [3], '/canvases/3/layout'],
    ['drawingMoves', [3], '/canvases/3/drawing/moves'],
    ['admin', [], '/admin'],
    ['users', [], '/admin/users'],
    ['user', [4], '/admin/users/4'],
    ['userPasswordReset', [4], '/admin/users/4/password-reset'],
    ['userSecondFactor', [4], '/admin/users/4/second-factor'],
    ['userSessions', [4], '/admin/users/4/sessions'],
    ['organisations', [], '/admin/organisations'],
    ['organisation', [2], '/admin/organisations/2'],
    ['organisationArchive', [2], '/admin/organisations/2/archive'],
    ['organisationRestore', [2], '/admin/organisations/2/restore'],
    ['organisationLogo', [2], '/organisations/2/logo'],
    ['auditLog', [], '/admin/audit-log'],
    ['canvases', [], '/canvases'],
    ['canvas', [7], '/canvases/7'],
    ['canvasArchive', [7], '/canvases/7/archive'],
    ['canvasRestore', [7], '/canvases/7/restore'],
    ['entry', [7], '/canvases/7/entry'],
    ['view', [7], '/canvases/7/view'],
    ['view', [7, 2026], '/canvases/7/view?year=2026'],
    ['elements', [7], '/canvases/7/elements'],
    ['element', [7, 'e1'], '/canvases/7/elements/e1'],
    ['elementReorder', [7, 'e1'], '/canvases/7/elements/e1/reorder'],
    ['elementArchive', [7, 'e1'], '/canvases/7/elements/e1/archive'],
    ['elementRestore', [7, 'e1'], '/canvases/7/elements/e1/restore'],
    ['quickEntryPreview', [7], '/canvases/7/quick-entry/preview'],
    ['quickEntry', [7], '/canvases/7/quick-entry'],
    ['statement', [7, 'mission'], '/canvases/7/statements/mission'],
    ['goals', [7], '/canvases/7/goals'],
    ['goal', [7, 3], '/canvases/7/goals/3'],
    ['goalReorder', [7, 3], '/canvases/7/goals/3/reorder'],
    ['template', [7], '/templates/7'],
    ['undo', ['tok'], '/undo/tok'],
];

describe('routes', () => {
    it.each(cases)('%s(%j) builds %s', (name, args, path) => {
        expect((routes[name] as (...a: (string | number)[]) => string)(...args)).toBe(path);
    });
});
