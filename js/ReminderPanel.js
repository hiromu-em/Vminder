export class ReminderPanel {
    constructor() {
        this.memberRemoveButtons = null;
        this.slidebarContainer = null;
        this.slideOpenButton = null;
        this.slidebarList = null;
        this.mediaQuery = window.matchMedia('(max-width: 768px)');

        this.handleMediaQueryChange(this.mediaQuery);
        this.mediaQuery.addEventListener('change', (e) => this.handleMediaQueryChange(e));
    }

    /**
     * メディアクエリの状態変化に応じてスライドバーの表示/非表示を切り替える
     */
    handleMediaQueryChange(event) {
        if (event.matches) {
            this.createSlidebar();
        } else {
            this.removeSlidebar();

            const checkboxes = document.querySelectorAll('.member-card input[type="checkbox"]');
            this.updatePanel(checkboxes, this.mediaQuery);
        }
    }

    /**
     * スライドバーのDOM要素を生成し、ページに追加する
     */
    createSlidebar() {

        if (document.querySelector('.slidebar-container')) return;

        const slidebarContainer = document.createElement('div');
        slidebarContainer.className = 'slidebar-container';

        const slidebarButtonContainer = document.createElement('div');
        slidebarButtonContainer.className = 'slidebar-button-container';

        const registerButton = document.createElement('button');
        registerButton.className = 'register-button';
        registerButton.setAttribute('form', 'reminder-select');
        registerButton.textContent = '登録';

        const resetButton = document.createElement('button');
        resetButton.className = 'reset-button';
        resetButton.textContent = '全て解除';

        slidebarButtonContainer.appendChild(registerButton);
        slidebarButtonContainer.appendChild(resetButton);

        const openButton = document.createElement('button');
        openButton.className = 'slide-open-button';
        openButton.textContent = '登録一覧';

        const slidebar = document.createElement('div');
        slidebar.className = 'slidebar';

        const slidebarList = document.createElement('ul');
        slidebarList.className = 'slidebar-list';

        slidebarContainer.appendChild(slidebar);
        slidebar.appendChild(slidebarList);
        slidebarContainer.appendChild(slidebarButtonContainer);

        const mainContentWrapper = document.querySelector('.main-content-wrapper');
        if (mainContentWrapper) {

            mainContentWrapper.appendChild(openButton);
            mainContentWrapper.appendChild(slidebarContainer);
        } else {
            return;
        }

        this.slidebarContainer = slidebarContainer;
        this.slideOpenButton = openButton;
        this.slidebarList = slidebarList;

        // 開くボタンのイベントリスナーを設定
        this.addSlidebarEventListeners();
        
        // スライドバーが作成されたら、現在のチェック状態を反映
        const checkboxes = document.querySelectorAll('.member-card input[type="checkbox"]');
        this.updatePanel(checkboxes, this.mediaQuery);
        
        // リセットボタンのイベントリスナーを設定
        this.allResetButtonEvent(checkboxes);

        this.memberRemoveButtons = Array.from(document.querySelectorAll('.remove-button'));
    }

    /**
     * スライドバーのDOM要素をページから削除する
     */
    removeSlidebar() {
        if (this.slidebarContainer) {
            this.slidebarContainer.remove();
            this.slidebarContainer = null;
        }
        if (this.slideOpenButton) {

            this.slideOpenButton.remove();
            this.slideOpenButton = null;
        }

        this.slidebarList = null;
    }

    /**
     * スライドバーを開くボタンにイベントリスナーを追加する
     */
    addSlidebarEventListeners() {

        this.slideOpenButton.addEventListener('click', () => {

            this.slidebarContainer.style.transform = 'translate(0)';
            this.slideOpenButton.style.opacity = '0';
            this.slideOpenButton.style.right = '-88px';
        });
    }

    /**
     * スライドバーの要素を取得する (外部からアクセスする場合)
     */
    getSlidebarElements() {
        return {
            container: this.slidebarContainer,
            openButton: this.slideOpenButton,
            list: this.slidebarList,
            removeButtons: this.memberRemoveButtons
        };
    }

    /**
     * リマインダー登録を全て解除
     */
    allResetButtonEvent(checkboxes) {
        const resetButton = document.querySelector('.reset-button');

        resetButton.addEventListener('click', () => {

            let changed = false;
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    checkbox.checked = false;
                    changed = true;
                }
            });
        
            if (changed) {
                this.updatePanel(checkboxes, this.mediaQuery);
            }
        });
    }

    /**
     * サイドバーまたはスライドバーの表示内容を更新する
     * @param {NodeListOf<HTMLInputElement>} checkboxes - チェックボックス要素のリスト
     * @param {MediaQueryList} mediaQuery - スライドバー表示を判定するためのメディアクエリ
     */
    updatePanel(checkboxes, mediaQuery) {

        const isSlidebar = mediaQuery.matches;

        const listElement = isSlidebar ? this.slidebarList : document.getElementById('sidebar-list');

        // 現在リストに表示されているメンバーIDを保持
        const currentMemberIds = new Set();
        listElement.querySelectorAll('li[data-member-id]').forEach(li => {
            currentMemberIds.add(li.dataset.memberId);
        });

        const membersToAdd = [];
        const membersToKeep = new Set();

        checkboxes.forEach(checkbox => {
            if (checkbox.checked) {
                const memberId = checkbox.value;
                membersToKeep.add(memberId);

                if (!currentMemberIds.has(memberId)) {
                    const memberCardLabel = checkbox.closest('.member-card');
                    const imgElement = memberCardLabel.querySelector('img');
                    const memberImgUrl = imgElement.src;

                    membersToAdd.push({ id: memberId, imgUrl: memberImgUrl });
                }
            }
        });

        // リストから削除する項目を特定して削除
        listElement.querySelectorAll('li[data-member-id]').forEach(li => {
            if (!membersToKeep.has(li.dataset.memberId)) {
                li.remove();
            }
        });

        membersToAdd.forEach(member => {
            const listItem = document.createElement('li');
            listItem.dataset.memberId = member.id;

            const icon = document.createElement('img');
            icon.src = member.imgUrl;
            icon.classList.add('sidebar-member-icon');

            const removeButton = document.createElement('button');
            removeButton.classList.add('remove-button');
            removeButton.dataset.memberId = member.id;
            removeButton.setAttribute('aria-label', `Remove member ${member.id}`);

            listItem.appendChild(icon);
            listItem.appendChild(removeButton);

            listElement.appendChild(listItem);
        });
    }
}
