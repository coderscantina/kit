import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { h } from 'vue'

import { Bubble, BubbleContent } from '~/components/ui/bubble'
import { InputField } from '~/components/ui/form'
import { Marker, MarkerContent } from '~/components/ui/marker'
import { Message, MessageContent } from '~/components/ui/message'
import {
  MessageScroller,
  MessageScrollerContent,
  MessageScrollerItem,
  MessageScrollerProvider,
  MessageScrollerViewport,
} from '~/components/ui/message-scroller'
import {
  Questionnaire,
  QuestionnaireChoice,
  QuestionnaireItem,
} from '~/components/ui/questionnaire'

describe('chat primitives', () => {
  it('flips a message and its bubble to the sender side', () => {
    const wrapper = mount(Message, {
      props: { align: 'end' },
      slots: {
        default: () =>
          h(MessageContent, () => h(Bubble, { align: 'end' }, () => h(BubbleContent, () => 'Hi'))),
      },
    })

    expect(wrapper.attributes('data-align')).toBe('end')
    expect(wrapper.get('[data-slot=bubble]').attributes('data-align')).toBe('end')
    expect(wrapper.get('[data-slot=bubble-content]').text()).toBe('Hi')
  })

  it('renders a marker between turns', () => {
    const wrapper = mount(Marker, {
      props: { variant: 'separator' },
      slots: { default: () => h(MarkerContent, () => 'Today') },
    })

    expect(wrapper.attributes('data-variant')).toBe('separator')
    expect(wrapper.text()).toBe('Today')
  })

  it('registers every turn with the scroller', () => {
    const wrapper = mount(MessageScrollerProvider, {
      slots: {
        default: () =>
          h(MessageScroller, () =>
            h(MessageScrollerViewport, () =>
              h(MessageScrollerContent, () =>
                ['a', 'b'].map((id) => h(MessageScrollerItem, { messageId: id, key: id }, () => id))
              )
            )
          ),
      },
    })

    expect(wrapper.findAll('[data-slot=message-scroller-item]')).toHaveLength(2)
    expect(wrapper.get('[data-message-id=b]').text()).toBe('b')
  })
})

describe('Questionnaire', () => {
  it('answers the active item through its choice', async () => {
    const wrapper = mount(Questionnaire, {
      slots: {
        default: () =>
          h(QuestionnaireItem, { name: 'tone' }, () => [
            h(QuestionnaireChoice, { value: 'short' }, () => 'Short'),
            h(QuestionnaireChoice, { value: 'long' }, () => 'Long'),
          ]),
      },
    })

    const item = wrapper.get('[data-slot=questionnaire-item]')
    expect(item.attributes('data-status')).toBe('unanswered')

    await wrapper.get('input[value=short]').setValue(true)

    expect(item.attributes('data-status')).toBe('answered')
  })
})

describe('InputField', () => {
  it('puts its actions in the group instead of over the text', async () => {
    const wrapper = mount(InputField, {
      props: { name: 'q', modelValue: 'hello', actions: ['clear'] },
    })

    const addon = wrapper.get('[data-slot=input-group-addon]')
    expect(addon.attributes('data-align')).toBe('inline-end')

    await addon.get('button').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([''])
  })

  it('marks the control invalid so the group can show it', () => {
    const wrapper = mount(InputField, {
      props: { name: 'q', error: 'Required' },
    })

    expect(wrapper.get('input').attributes('aria-invalid')).toBe('true')
  })
})
