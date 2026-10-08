{{-- Общая модалка задачи: вкладки «Этапы» / «Чек-лист» / «История» / «Комментарии».
     Подключается на доске и на странице «Мои задачи»; требует Alpine-компонент
     с полями modal / descOpen / descText / dueText / priorityText / newItem / commentBody
     и методами из taskModalMethods(). --}}
<div x-show="modal.open" x-cloak @keydown.escape.window="modal.open = false"
     class="fixed inset-0 z-40 flex items-center justify-center p-0 md:p-4">
    <div class="absolute inset-0 bg-black/40" @click="modal.open = false"></div>
    <div class="relative bg-white border-0 md:border border-line rounded-none md:rounded-xl shadow-lg w-full md:max-w-xl h-full md:h-auto md:max-h-[90vh] flex flex-col">
        <div class="p-5 pb-0 border-b border-line">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-[16px] font-semibold leading-tight">
                        <span x-text="modal.data.task"></span> — <span x-text="modal.data.project"></span>
                    </h2>
                    <p class="text-[13px] text-muted mt-0.5">
                        Ответственный: <span class="text-ink font-medium" x-text="modal.data.responsible"></span>
                    </p>

                    {{-- Бейджи трекера: приоритет и дедлайн (этап 11) --}}
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <template x-if="modal.data.priority === 'high'">
                            <span class="rounded bg-red-50 text-danger font-medium px-2 py-0.5 text-[11px]">Высокий приоритет</span>
                        </template>
                        <template x-if="modal.data.priority === 'low'">
                            <span class="rounded bg-slate-100 text-muted font-medium px-2 py-0.5 text-[11px]">Низкий приоритет</span>
                        </template>
                        <template x-if="modal.data.due_date">
                            <span class="rounded px-2 py-0.5 text-[11px] font-medium"
                                  :class="dueToneClass(modal.data.due)">
                                Дедлайн: <span x-text="modal.data.due_date"></span><template x-if="modal.data.due && modal.data.due.label"><span> · <span x-text="modal.data.due.label"></span></span></template>
                            </span>
                        </template>
                    </div>

                    {{-- Раскрывающийся блок «Описание» --}}
                    <div class="mt-2">
                        <button type="button" @click="descOpen = !descOpen"
                                class="text-[12px] font-medium text-accent hover:underline">
                            <span x-text="descOpen ? 'Скрыть описание' : 'Описание'"></span>
                        </button>
                        <div x-show="descOpen" x-cloak class="mt-2">
                            <template x-if="modal.data.can_edit">
                                <div class="space-y-2">
                                    <textarea rows="3" x-model="descText" maxlength="5000"
                                              placeholder="Описание задачи…"
                                              class="w-full rounded-lg border border-line px-3 py-2 text-[13px] resize-y focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent"></textarea>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <input type="date" x-model="dueText" title="Дедлайн задачи"
                                               class="rounded-lg border border-line px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                                        <select x-model="priorityText" title="Приоритет"
                                                class="rounded-lg border border-line px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                                            <option value="low">Низкий</option>
                                            <option value="normal">Обычный</option>
                                            <option value="high">Высокий</option>
                                        </select>
                                        <button type="button" @click="saveTracker()"
                                                class="rounded-lg bg-accent hover:bg-accent-hover text-white text-[13px] font-medium px-3 py-1.5 transition-colors">
                                            Сохранить
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!modal.data.can_edit">
                                <p class="text-[13px] text-muted whitespace-pre-line break-words"
                                   x-text="(modal.data.description && modal.data.description.length) ? modal.data.description : 'Описание не задано.'"></p>
                            </template>
                        </div>
                    </div>
                </div>
                <button type="button" @click="modal.open = false" class="text-muted hover:text-ink text-[18px] leading-none">&times;</button>
            </div>

            {{-- Вкладки --}}
            <div class="flex gap-4 mt-3 text-[13px] font-medium">
                <button type="button" @click="modal.tab = 'stages'"
                        :class="modal.tab === 'stages' ? 'text-accent border-accent' : 'text-muted border-transparent hover:text-ink'"
                        class="pb-2 border-b-2">Этапы</button>
                <button type="button" @click="modal.tab = 'checklist'"
                        :class="modal.tab === 'checklist' ? 'text-accent border-accent' : 'text-muted border-transparent hover:text-ink'"
                        class="pb-2 border-b-2">Чек-лист<template x-if="modal.data.items && modal.data.items.total"><span class="ml-1 text-[11px] text-muted" x-text="'(' + modal.data.items.done + '/' + modal.data.items.total + ')'"></span></template></button>
                <button type="button" @click="modal.tab = 'history'"
                        :class="modal.tab === 'history' ? 'text-accent border-accent' : 'text-muted border-transparent hover:text-ink'"
                        class="pb-2 border-b-2">История</button>
                <button type="button" @click="modal.tab = 'comments'"
                        :class="modal.tab === 'comments' ? 'text-accent border-accent' : 'text-muted border-transparent hover:text-ink'"
                        class="pb-2 border-b-2">Комментарии</button>
            </div>
        </div>

        <div class="p-5 overflow-y-auto flex-1">
            <div x-show="modal.loading" x-cloak class="py-10 text-center text-[13px] text-muted">Загружаем…</div>

            {{-- Вкладка «Этапы» --}}
            <div x-show="!modal.loading && modal.tab === 'stages'" x-cloak class="space-y-2">
                <template x-for="st in modal.data.stages">
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-[13px] font-medium truncate" x-text="st.name"></span>
                            <span x-show="st.status === 'pending'"
                                  class="rounded bg-slate-100 text-muted px-2 py-0.5 text-[11px] shrink-0">Ожидает</span>
                            <span x-show="st.status === 'in_progress'"
                                  class="rounded bg-accent/10 text-accent px-2 py-0.5 text-[11px] shrink-0">В работе · <span x-text="st.entered_at"></span></span>
                            <span x-show="st.status === 'done'"
                                  class="rounded bg-green-50 text-done px-2 py-0.5 text-[11px] shrink-0">Завершено</span>
                        </div>
                        <div class="flex gap-1.5 shrink-0" x-show="modal.data.can_move">
                            <button x-show="st.status === 'pending'" type="button" @click="doStage(st, 'start')"
                                    class="rounded-lg border border-accent/40 text-accent px-3 md:px-2.5 py-2 md:py-1 min-h-[44px] md:min-h-0 text-[12px] font-medium hover:border-accent">Начать</button>
                            <button x-show="st.status === 'in_progress'" type="button" @click="doStage(st, 'finish')"
                                    class="rounded-lg border border-line px-3 md:px-2.5 py-2 md:py-1 min-h-[44px] md:min-h-0 text-[12px] font-medium hover:bg-page">Завершить</button>
                            <button x-show="st.status === 'done'" type="button" @click="doStage(st, 'reopen')"
                                    class="rounded-lg border border-accent/40 text-accent px-3 md:px-2.5 py-2 md:py-1 min-h-[44px] md:min-h-0 text-[12px] font-medium hover:border-accent">Вернуть в работу</button>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Вкладка «Чек-лист» --}}
            <div x-show="!modal.loading && modal.tab === 'checklist'" x-cloak>
                <template x-if="modal.data.items && modal.data.items.can_manage">
                    <div class="flex gap-2 mb-3">
                        <input type="text" x-model="newItem" @keydown.enter.prevent="addItem()" maxlength="255"
                               placeholder="Новый пункт…"
                               class="flex-1 rounded-lg border border-line px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent">
                        <button type="button" @click="addItem()"
                                class="rounded-lg border border-accent/40 text-accent px-3 py-2 text-[13px] font-medium hover:border-accent">
                            Добавить
                        </button>
                    </div>
                </template>

                <div class="space-y-1.5">
                    <template x-for="it in (modal.data.items ? modal.data.items.items : [])" :key="it.id">
                        <div class="flex items-center gap-2.5 rounded-lg border border-line px-3 py-2">
                            <input type="checkbox" :checked="it.is_done"
                                   @change="toggleItem(it)"
                                   :disabled="!(modal.data.items && modal.data.items.can_manage)"
                                   class="w-4 h-4 shrink-0 rounded border-line text-accent focus:ring-accent/30">
                            <span class="flex-1 text-[13px] break-words"
                                  :class="it.is_done ? 'line-through text-muted' : 'text-ink'"
                                  x-text="it.title"></span>
                            <button x-show="modal.data.items && modal.data.items.can_manage" type="button"
                                    @click="removeItem(it.id)"
                                    class="text-muted hover:text-danger leading-none shrink-0" title="Удалить">&times;</button>
                        </div>
                    </template>
                </div>
                <p x-show="modal.data.items && modal.data.items.total === 0" class="text-[13px] text-muted">
                    Чек-лист пуст.
                </p>
                <p x-show="modal.data.items && modal.data.items.can_manage === false" class="text-[12px] text-muted mt-2">
                    Отмечать пункты может ответственный или руководитель.
                </p>
            </div>

            {{-- Вкладка «История» --}}
            <div x-show="!modal.loading && modal.tab === 'history'" x-cloak class="space-y-1.5">
                <template x-for="h in modal.data.history">
                    <div class="flex gap-2 text-[13px] leading-snug">
                        <span class="text-muted whitespace-nowrap" x-text="h.time"></span>
                        <span class="text-muted shrink-0" x-text="h.user + ':'"></span>
                        <span class="min-w-0" x-text="h.text"></span>
                    </div>
                </template>
                <p x-show="modal.data.history.length === 0" class="text-[13px] text-muted">Пока нет записей.</p>
            </div>

            {{-- Вкладка «Комментарии» --}}
            <div x-show="!modal.loading && modal.tab === 'comments'" x-cloak>
                {{-- Форма: ответственный или руководитель --}}
                <template x-if="modal.canWrite">
                    <div>
                        <textarea x-ref="commentInput" rows="2" x-model="commentBody"
                                  @input="autoGrow($el)"
                                  @keydown.ctrl.enter="submitComment()"
                                  placeholder="Написать комментарий…"
                                  class="w-full rounded-lg border border-line px-3 py-2 text-sm resize-none overflow-hidden focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent"></textarea>
                        <div class="flex justify-end mt-1.5">
                            <button type="button" @click="submitComment()"
                                    class="rounded-lg bg-accent hover:bg-accent-hover text-white text-sm font-medium px-4 py-1.5 transition-colors">
                                Отправить
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Подпись для тех, кто не может комментировать --}}
                <template x-if="!modal.canWrite">
                    <p class="text-[12px] text-muted rounded-lg bg-page border border-line px-3 py-2">
                        Комментировать может ответственный или руководитель.
                    </p>
                </template>

                {{-- Лента комментариев --}}
                <div class="space-y-3.5 mt-4">
                    <template x-for="c in modal.comments" :key="c.id">
                        <div class="flex gap-2.5">
                            <span class="w-8 h-8 rounded-full shrink-0 inline-flex items-center justify-center text-[11px] font-bold"
                                  :class="c.user.color" x-text="c.user.initials"></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-baseline gap-2">
                                    <span class="text-[13px] font-semibold" x-text="c.user.name"></span>
                                    <span class="text-[11px] text-muted whitespace-nowrap" x-text="c.time"></span>
                                </div>
                                <p class="text-[13px] leading-relaxed mt-0.5 break-words" x-html="nl2brHtml(c.body)"></p>
                            </div>
                            <button x-show="c.canDelete" type="button"
                                    @click="if (confirm('Удалить комментарий?')) removeComment(c.id)"
                                    class="text-muted hover:text-danger leading-none shrink-0 mt-0.5" title="Удалить">&times;</button>
                        </div>
                    </template>
                    <p x-show="modal.comments.length === 0" class="text-[13px] text-muted">Комментариев пока нет.</p>
                </div>
            </div>
        </div>
    </div>
</div>
