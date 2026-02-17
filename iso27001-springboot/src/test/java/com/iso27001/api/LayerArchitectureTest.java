package com.iso27001.api;

import com.tngtech.archunit.core.domain.JavaClasses;
import com.tngtech.archunit.core.importer.ClassFileImporter;
import com.tngtech.archunit.lang.ArchRule;
import org.junit.jupiter.api.Test;

import static com.tngtech.archunit.lang.syntax.ArchRuleDefinition.noClasses;

/**
 * A.14 — DDD layer boundary enforcement via ArchUnit.
 * Mirrors check-layers.ts (NestJS) and .importlinter (FastAPI).
 *
 * Rules:
 *   1. domain must not depend on infrastructure, api, or core
 *   2. infrastructure must not depend on api
 *   3. core must not depend on api
 */
class LayerArchitectureTest {

    private static final String BASE = "com.iso27001.api";

    private final JavaClasses classes = new ClassFileImporter()
        .importPackages(BASE);

    @Test
    void domainMustNotDependOnInfrastructure() {
        ArchRule rule = noClasses()
            .that().resideInAPackage(BASE + ".domain..")
            .should().dependOnClassesThat()
            .resideInAPackage(BASE + ".infrastructure..");
        rule.check(classes);
    }

    @Test
    void domainMustNotDependOnApi() {
        ArchRule rule = noClasses()
            .that().resideInAPackage(BASE + ".domain..")
            .should().dependOnClassesThat()
            .resideInAPackage(BASE + ".api..");
        rule.check(classes);
    }

    @Test
    void domainMustNotDependOnCore() {
        ArchRule rule = noClasses()
            .that().resideInAPackage(BASE + ".domain..")
            .should().dependOnClassesThat()
            .resideInAPackage(BASE + ".core..");
        rule.check(classes);
    }

    @Test
    void infrastructureMustNotDependOnApi() {
        ArchRule rule = noClasses()
            .that().resideInAPackage(BASE + ".infrastructure..")
            .should().dependOnClassesThat()
            .resideInAPackage(BASE + ".api..");
        rule.check(classes);
    }

    @Test
    void coreMustNotDependOnApi() {
        ArchRule rule = noClasses()
            .that().resideInAPackage(BASE + ".core..")
            .should().dependOnClassesThat()
            .resideInAPackage(BASE + ".api..");
        rule.check(classes);
    }
}
