export function focusFirstInvalid(root: ParentNode = document): boolean {
    const field = root.querySelector<HTMLElement>('[aria-invalid="true"]');
    field?.focus();
    return field !== null;
}
