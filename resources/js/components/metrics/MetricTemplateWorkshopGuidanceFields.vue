<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

defineProps<{
    workshopPrompt: string;
    workshopGuidance: string;
    index: number;
    errors: Record<string, string>;
}>();

const emit = defineEmits<{
    (e: 'update:workshopPrompt', value: string): void;
    (e: 'update:workshopGuidance', value: string): void;
}>();
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <div class="grid gap-2">
            <Label :for="`field-workshop-prompt-${index}`">
                Workshop prompt
            </Label>
            <Textarea
                :id="`field-workshop-prompt-${index}`"
                :model-value="workshopPrompt"
                @update:model-value="emit('update:workshopPrompt', $event)"
                placeholder="Agree which demand stage is official for board CPL."
            />
            <p class="text-xs text-muted-foreground">
                Consultant-facing question to resolve during workshop prep.
            </p>
            <p
                v-if="
                    errors[`form_schema_json.fields.${index}.workshop_prompt`]
                "
                class="text-sm text-red-600"
            >
                {{ errors[`form_schema_json.fields.${index}.workshop_prompt`] }}
            </p>
        </div>

        <div class="grid gap-2">
            <Label :for="`field-workshop-guidance-${index}`">
                Workshop guidance
            </Label>
            <Textarea
                :id="`field-workshop-guidance-${index}`"
                :model-value="workshopGuidance"
                @update:model-value="emit('update:workshopGuidance', $event)"
                placeholder="Explain common disagreement patterns and how the facilitator should interpret them."
            />
            <p class="text-xs text-muted-foreground">
                Internal guidance for interpreting stakeholder disagreement.
            </p>
            <p
                v-if="
                    errors[`form_schema_json.fields.${index}.workshop_guidance`]
                "
                class="text-sm text-red-600"
            >
                {{
                    errors[`form_schema_json.fields.${index}.workshop_guidance`]
                }}
            </p>
        </div>
    </div>
</template>
