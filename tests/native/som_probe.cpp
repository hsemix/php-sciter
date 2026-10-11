
#include <cstdlib>
#include <cstring>
#include <cstdio>
#include <dlfcn.h>

#include "sciter-om.h"
#include "sciter-om-def.h"

#include <string>

using ValueStringDataFn = UINT (*)(
    const VALUE*,
    LPCWSTR*,
    UINT*
);

static ValueStringDataFn value_string_data = nullptr;

extern "C" void som_probe_set_value_string_data(
    uintptr_t address
)
{
    value_string_data =
        reinterpret_cast<ValueStringDataFn>(address);

    fprintf(stderr,
        "[native SOM] ValueStringData configured: %p\n",
        reinterpret_cast<void*>(address)
    );
}

using ValueStringDataSetFn = UINT (*)(
    SCITER_VALUE* value,
    const WCHAR* text,
    UINT length,
    UINT units
);

static ValueStringDataSetFn value_string_data_set = nullptr;

extern "C" void som_probe_set_value_string_data_set(
    uintptr_t address
)
{
    value_string_data_set =
        reinterpret_cast<ValueStringDataSetFn>(address);
}



using PhpGreetCallback = void (*)(
    const char* name,
    char* output,
    size_t output_size
);

static PhpGreetCallback php_greet_callback = nullptr;

extern "C" void som_probe_set_greet_callback(
    PhpGreetCallback callback
)
{
    php_greet_callback = callback;
}





struct php_som_probe
{
    som_asset_t base;
    som_passport_t passport;
    som_method_def_t methods[1];
    long references;
};

/*
 * Reference counting
 *
 * Keep the allocation alive during this diagnostic.
 * Sciter may retain references until JavaScript runtime shutdown.
 */

static long probe_add_ref(som_asset_t *thing)
{
    auto *probe = reinterpret_cast<php_som_probe *>(thing);

    ++probe->references;

    fprintf(stderr,
            "[native SOM] add_ref: %ld\n",
            probe->references);

    return probe->references;
}

static long probe_release(som_asset_t *thing)
{
    auto *probe = reinterpret_cast<php_som_probe *>(thing);

    if (probe->references > 0)
        --probe->references;

    fprintf(stderr,
            "[native SOM] release: %ld\n",
            probe->references);

    // Diagnostic only: do not free during shutdown testing.
    return probe->references;
}

static long probe_get_interface(
    som_asset_t *thing,
    const char *name,
    void **out)
{
    (void)thing;
    (void)name;

    if (out)
        *out = nullptr;

    return 0;
}

static som_passport_t *probe_get_passport(som_asset_t *thing)
{
    auto *probe = reinterpret_cast<php_som_probe *>(thing);

    return &probe->passport;
}

/*
 * Native greet method.
 *
 * First test: verify JavaScript can invoke the method.
 * Value conversion will be implemented next.
 */

static SBOOL probe_greet(
    som_asset_t* thing,
    UINT argc,
    const SCITER_VALUE* argv,
    SCITER_VALUE* result
)
{
    (void)thing;

    fprintf(stderr,
        "[native SOM] greet() called with %u argument(s)\n",
        argc);

    if (argc == 0 || argv == nullptr || result == nullptr)
    {
        return FALSE;
    }

    if (!php_greet_callback ||
        !value_string_data ||
        !value_string_data_set)
    {
        fprintf(stderr,
            "[native SOM] Required callbacks are not configured\n");

        return FALSE;
    }

    // Step 1: Extract the JavaScript string.
    const WCHAR* text = nullptr;
    UINT length = 0;

    UINT status = value_string_data(
        &argv[0],
        &text,
        &length
    );

    fprintf(stderr,
        "[native SOM] ValueStringData status: %u, length: %u\n",
        status,
        length);

    if (status != 0 || text == nullptr)
    {
        return FALSE;
    }

    // Step 2: Convert UTF-16 to ASCII (temporary).
    std::string name;
    name.reserve(length);

    for (UINT i = 0; i < length; ++i)
    {
        if (text[i] > 0x7F)
        {
            fprintf(stderr,
                "[native SOM] Non-ASCII input not yet supported\n");

            return FALSE;
        }

        name.push_back(static_cast<char>(text[i]));
    }

    fprintf(stderr,
        "[native SOM] Passing '%s' to PHP\n",
        name.c_str());

    // Step 3: Let PHP generate the greeting.
    char output[1024] = {};

    php_greet_callback(
        name.c_str(),
        output,
        sizeof(output)
    );

    // Ensure the buffer is null-terminated.
    output[sizeof(output) - 1] = '\0';

    fprintf(stderr,
        "[native SOM] PHP returned '%s'\n",
        output);

    // Step 4: Convert the PHP result to UTF-16.
    std::basic_string<WCHAR> greeting;

    for (const unsigned char* p =
             reinterpret_cast<const unsigned char*>(output);
         *p;
         ++p)
    {
        if (*p > 0x7F)
        {
            fprintf(stderr,
                "[native SOM] Non-ASCII output not yet supported\n");

            return FALSE;
        }

        greeting.push_back(static_cast<WCHAR>(*p));
    }

    // Step 5: Return the PHP result to JavaScript.
    UINT setStatus = value_string_data_set(
        result,
        greeting.data(),
        static_cast<UINT>(greeting.size()),
        0
    );

    fprintf(stderr,
        "[native SOM] ValueStringDataSet status: %u\n",
        setStatus);

    return setStatus == 0 ? TRUE : FALSE;
}

/*
 * Native SOM class table.
 *
 * This must remain valid while Sciter retains asset references.
 */

static som_asset_class_t probe_class = {
    probe_add_ref,
    probe_release,
    probe_get_interface,
    probe_get_passport};

/*
 * Create a native SOM asset.
 */

extern "C" som_asset_t *som_probe_create(
    unsigned long long name_atom,
    unsigned long long greet_atom)
{
    auto *probe = static_cast<php_som_probe *>(
        calloc(1, sizeof(php_som_probe)));

    if (!probe)
        return nullptr;

    probe->base.isa = &probe_class;

    probe->passport.flags = 0;
    probe->passport.name = static_cast<som_atom_t>(name_atom);

    probe->methods[0].reserved = nullptr;
    probe->methods[0].name = static_cast<som_atom_t>(greet_atom);
    probe->methods[0].params = 1;
    probe->methods[0].func = probe_greet;

    probe->passport.methods = probe->methods;
    probe->passport.n_methods = 1;

    fprintf(stderr,
            "[native SOM] asset created with greet method\n");

    return &probe->base;
}

/*
 * Diagnostic: inspect reference count.
 */

extern "C" long som_probe_refs(som_asset_t *thing)
{
    if (!thing)
        return -1;

    auto *probe = reinterpret_cast<php_som_probe *>(thing);

    return probe->references;
}

/*
 * Diagnostic: verify the native library remains callable.
 */

extern "C" void som_probe_ping()
{
    fprintf(stderr,
            "[native SOM] library is still callable\n");
}

/*
 * Pin the native library in memory.
 *
 * This prevents its SOM function pointers from becoming invalid
 * before Sciter finishes JavaScript runtime cleanup.
 */

extern "C" int som_probe_pin_library()
{
    Dl_info info{};

    if (!dladdr(
            reinterpret_cast<const void *>(&som_probe_pin_library),
            &info))
    {
        return 0;
    }

    if (!info.dli_fname)
        return 0;

    void *handle = dlopen(
        info.dli_fname,
        RTLD_NOW | RTLD_NODELETE);

    return handle != nullptr ? 1 : 0;
}
