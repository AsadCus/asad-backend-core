// import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                {/* <AppLogoIcon className="size-5 fill-current text-white dark:text-black" /> */}
                <img
                    src="/compact-logo-primary.png"
                    alt="TMS"
                    className="hidden w-4"
                    // className="hidden w-4 not-dark:block"
                />
                <img
                    src="/compact-logo-light.png"
                    alt="TMS"
                    // className="hidden w-4"
                    className="hidden w-4 not-dark:block"
                />
                <img
                    src="/compact-logo-dark.png"
                    alt="TMS"
                    className="hidden w-4 dark:block"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left text-base">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    TMS
                </span>
            </div>
        </>
    );
}
