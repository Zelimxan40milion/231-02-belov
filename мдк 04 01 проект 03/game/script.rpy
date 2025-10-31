
init python:
    def update_rating(is_correct):
        # Если у вас еще нет переменной rating, инициализируйте ее
        if not hasattr(store, 'rating'):
            store.rating = 0
        
        if is_correct:
            store.rating += 1  # Увеличиваем рейтинг за правильный ответ
        else:
            store.rating = max(0, store.rating - 1)  # Уменьшаем рейтинг, но не ниже 0
        
        return store.rating

# Определяем переменные для системы рейтинга
default correct_answers = 0
default total_questions = 0
default rating = 0.0

# Персонажи
define m = Character("Медсестра")
define p = Character("Макан")
define d = Character("Доктор")

# Начало игры
label start:
    scene bg polik
    show medsis at right
    play music "audio/background_music.mp3" fadein 2.0
    
    m "Добро пожаловать на курс по первой помощи! Сегодня мы научимся правильно обрабатывать порезы и ссадины."
    m "Ваша задача - принимать правильные решения в различных ситуациях. От этого будет зависеть ваш итоговый рейтинг."
    
    jump scene1

# Сцена 1: Неглубокий порез
label scene1:
    scene bg kit
    show macan1
    
    p "Ой! Я порезал палец ножом пока готовил салат! Что мне делать?"
    
    menu:
        "Промыть под холодной водой и наложить повязку":
            $ update_rating(True)
            m "Правильно! Сначала нужно промыть рану, чтобы удалить загрязнения."
            jump scene2
            
        "Посыпать солью для дезинфекции":
            $ update_rating(False)
            m "Нет, это вызовет сильную боль и может повредить ткани. Не используйте соль!"
            jump scene2
            
        "Протереть спиртом и заклеить пластырем":
            $ update_rating(False)
            m "Спирт слишком агрессивен для свежей раны. Он замедлит заживление."
            jump scene2

# Сцена 2: Загрязненная ссадина
label scene2:
    scene bg park
    show macan3
    
    p "Я упал с велосипеда и ободрил колено. Рана грязная, что делать?"
    
    menu:
        "Промыть перекисью водорода и наложить стерильную повязку":
            $ update_rating(True)
            m "Верно! Перекись водорода хорошо очищает от загрязнений."
            jump scene3
            
        "Протереть грязным платком и оставить как есть":
            $ update_rating(False)
            m "Никогда не сыпьте землю в рану! Это вызовет серьезное заражение."
            jump scene3

# Сцена 3: Глубокий порез
label scene3:
    scene mast
    show macan2
    
    
    p "Я сильно порезался стеклом! Кровь течет сильно..."
    
    menu:
        "Поднять руку выше сердца и наложить давящую повязку":
            $ update_rating(True)
            m "Правильно! Это поможет остановить кровотечение."
            jump scene4
            
        "Наложить жгут на руку":
            $ update_rating(False)
            m "Жгут нужен только при артериальном кровотечении. Сначала попробуйте давящую повязку."
            jump scene4
            
        "Засыпать рану мукой":
            $ update_rating(False)
            m "Нет! Пищевые продукты не стерильны и вызовут инфекцию."
            jump scene4

# Сцена 4: Инфицированная рана
label scene4:
    scene home
    show macan4
    
    p "Моя ранка покраснела, опухла и болит. Кажется, она инфицирована..."
    
    menu:
        "Обратиться к врачу для назначения лечения":
            $ update_rating(True)
            m "Верное решение! Признаки инфекции требуют медицинской помощи."
            jump scene5
            
        "Прогреть рану для 'созревания'":
            $ update_rating(False)
            m "Тепло усилит воспаление! При инфекции нужно к врачу."
            jump scene5
            
        "Проткнуть пузыри и выдавить гной":
            $ update_rating(False)
            m "Никогда не делайте этого самостоятельно! Можно усугубить инфекцию."
            jump scene5

# Сцена 5: Заживающая рана
label scene5:
    scene palata
    show happydok at left

    
    d "Рана заживает, но образовалась корочка. Что нужно делать?"
    
    menu:
        "Не трогать корочку и содержать в чистоте":
            $ update_rating(True)
            m "Правильно! Корочка защищает рану во время заживления."
            jump final_results
            
        "Содрать корочку чтобы 'проветрить' рану":
            $ update_rating(False)
            m "Нет! Это замедлит заживление и может оставить шрам."
            jump final_results
            
        "Мазать жирным кремом чтобы размягчить":
            $ update_rating(False)
            m "Не стоит. Корочка должна отпасть естественным путем."
            jump final_results

# Функция обновления рейтинга
init python:
    def update_rating(is_correct):
        # Если у Вас еще нет переменной rating, инициализируйте
        if not hasattr(store, 'rating'):
            store.rating = 50  # или другое начальное значение
        
        if not hasattr(store, 'correct_answers'):
            store.correct_answers = 0
        if not hasattr(store, 'total_questions'):
            store.total_questions = 0
        
        if is_correct:
            store.correct_answers += 1
        store.total_questions += 1
        
        # Расчет рейтинга в процентах
        if store.total_questions > 0:
            store.rating = (store.correct_answers / store.total_questions) * 100
# Финальные результаты
label final_results:
    scene kabinet
    
    
    m "Курс завершен! Ваш итоговый рейтинг правильности"
    
    # Определяем концовку по рейтингу
    if rating >= 90:
        jump ending_perfect
    elif rating >= 70:
        jump ending_good
    elif rating >= 50:
        jump ending_average
    elif rating >= 30:
        jump ending_poor
    else:
        jump ending_bad

# Концовки
label ending_perfect:
    show happymed
    m " Блестящий результат! Вы настоящий эксперт по первой помощи!"
    m "Ваши знания могут спасти чью-то жизнь. Продолжайте в том же духе!"
    jump credits

label ending_good:
    show happymed
    m " Очень хороший результат! Вы хорошо усвоили основные принципы."
    m "С такими знаниями вы сможете грамотно помочь в большинстве ситуаций."
    jump credits

label ending_average:
    show medsis
    m " Неплохо, но есть над чем поработать."
    m "Рекомендую повторить материал о дезинфекции и перевязке ран."
    jump credits

label ending_poor:
    show normed
    m " Вам нужно серьезно заняться изучением первой помощи."
    m "Неправильные действия могут навредить! Пройдите курс еще раз."
    jump credits

label ending_bad:
    show anlakmed
    m " Результат тревожный. Вы совершали опасные ошибки."
    m "Настоятельно рекомендую пройти обучение у профессионалов!"
    jump credits

# Титры
label credits:
    scene black    
    show macan :
        xalign 0.5 yalign 0.5
    
    "Правильных ответов: [correct_answers] из [total_questions]"
    "Рейтинг: [rating]%%"
    "\n"
    "Спасибо за прохождение курса!"
    "Помните: правильная первая помощь может спасти жизнь."
    "\n"
    "Конец игры"
    
    
    return