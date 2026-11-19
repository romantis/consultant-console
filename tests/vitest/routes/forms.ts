const resolveId = (form: string | number | { id: string | number }) => {
    if (typeof form === 'object' && form !== null && 'id' in form) {
        return String(form.id);
    }

    return String(form);
};

export const autosave = (form: string | number | { id: string | number }) =>
    `/forms/${resolveId(form)}/autosave`;

export const submit = (form: string | number | { id: string | number }) =>
    `/forms/${resolveId(form)}/submit`;

export const show = (form: string | number | { id: string | number }) =>
    `/forms/${resolveId(form)}`;

export default {
    autosave,
    submit,
    show,
};
