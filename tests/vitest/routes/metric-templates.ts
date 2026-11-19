const resolveTemplateId = (
    metricTemplate: string | number | { id: string | number },
) => {
    if (typeof metricTemplate === 'object') {
        return String(metricTemplate.id);
    }

    return String(metricTemplate);
};

export const index = () => '/metric-templates';
export const create = () => '/metric-templates/create';
export const store = () => '/metric-templates';
export const edit = (metricTemplate: string | number | { id: string | number }) =>
    `/metric-templates/${resolveTemplateId(metricTemplate)}/edit`;
export const preview = (metricTemplate: string | number | { id: string | number }) =>
    `/metric-templates/${resolveTemplateId(metricTemplate)}/preview`;
export const update = (metricTemplate: string | number | { id: string | number }) =>
    `/metric-templates/${resolveTemplateId(metricTemplate)}`;
export const destroy = (metricTemplate: string | number | { id: string | number }) =>
    `/metric-templates/${resolveTemplateId(metricTemplate)}`;

const metricTemplates = {
    index,
    create,
    store,
    edit,
    preview,
    update,
    destroy,
};

export default metricTemplates;
